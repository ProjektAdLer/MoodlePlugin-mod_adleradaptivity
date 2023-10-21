<?php

namespace mod_adleradaptivity\local;

global $CFG;
require_once($CFG->libdir . '/questionlib.php');

use moodle_exception;
use question_answer;
use question_attempt;
use question_usage_by_activity;
use stdClass;

class completion_helpers {
    /** Check if task is completed. If task is optional, it is considered completed.
     *
     * @param question_usage_by_activity $quba The question usage object.
     * @param stdClass $task The task object.
     * @return bool
     * @throws moodle_exception
     */
    public static function check_task_completed(question_usage_by_activity $quba, stdClass $task): bool {
        $task_success = false;

        // handle task optional
        if ($task->optional) {
            $task_success = true;
        }

        foreach(helpers::load_questions_by_task_id($task->id) as $question) {
            // get slot of question
            foreach($quba->get_slots() as $slot) {
                if ($quba->get_question($slot)->id == $question->questionid) {
                    $slot_of_question = $slot;
                    break;
                }
            }
            if (!$slot_of_question) {
                throw new moodle_exception('question_not_found', 'question', '', null, 'Question for slot not found');
            }

            // check whether question was answered correctly
            $question_attempt = $quba->get_question_attempt($slot_of_question);
            $is_correct = self::check_question_correctly_answered($question_attempt);

            // if question was answered correctly and question difficulty is equal or above required_difficulty, set task_success to true
            if ($is_correct && $question->difficulty >= $task->required_difficulty) {
                $task_success = true;
            }
        }

        return $task_success;
    }

    /**
     * Check if question was answered correctly.
     * load correct question state. The one in the database is garbage.
     * It has to be recalculated every time it is required. The following code was originally taken from
     * question/type/rendererbase.php -> combined_feedback()
     *
     * @param question_attempt $question_attempt The question attempt object.
     * @return bool True if the question was answered correctly, false otherwise.
     * @throws moodle_exception
     */
    public static function check_question_correctly_answered(question_attempt $question_attempt): bool {
        $question = $question_attempt->get_question();

        $response = $question_attempt->get_last_qt_data();
        // This method calculates the state of the question.
        // It takes the $fractions from the questions and returns the following states
        // fraction <= 0: question_state::$gradedwrong
        // fraction = 1: question_state::$gradedright
        // else (<1 && > 0): question_state::$gradedpartial
        // This should also work just fine for manual grading because this also sets the fraction value like automatic grading.
        list($fraction, $state) = $question->grade_response($response);

        // this returns true if $state is of types question_state::$gradedright or question_state::$mangrright
        // and therefore is also correct for manual grading, although manual grading is irrelevant here because the
        // state object used here is calculated above and therefore always of type "automatic grading"
        return $state->is_correct();
    }

    /**
     * Determines whether an answer of a multichoice question type is judged as correct or not.
     * It's following the implementation from question/type/multichoice/renderer.php
     * qtype_multichoice_multi_renderer::is_correct() answers with fraction above 0 are considered correct
     * qtype_multichoice_single_renderer::is_correct() as i understand this, the value is always 0 or 1
     *   for single choice because the sum of all answers (one allowed) has to be 100%. Therefore, this returns just the
     *   "fraction" value of the answer
     * Summarized this means that the answer is correct if the fraction is above 0.
     *
     * @param question_answer $answer The answer object.
     * @return bool True if the question was answered correctly, false otherwise.
     */
    private static function is_multichoice_answer_correct(question_answer $answer): bool {
        return $answer->fraction > 0;
    }

    /**
     * Gives detailed information about the answer of a user for a question.
     * It returns an array with an entry for each choice of the question.
     * Each entry contains the following fields
     * - checked: bool, true if the choice was checked by the user
     * - answer_correct: bool, true if the users' choice is correct
     *
     * @param question_attempt $question_attempt The question attempt object.
     * @return array|null
     */
    public static function get_question_answer_details(question_attempt $question_attempt): array|null {
        $question = $question_attempt->get_question();
        $response = $question_attempt->get_last_qt_data();

        $answer_details = [];

        // if there is no attempt, return null, otherwise return the answer details
        if ($question_attempt->get_last_step()->get_fraction() === null) {
            $answer_details = null;
        } else {
            // differentiate between single and multiple choice questions
            // switch case over class type of $question
            switch (get_class($question)) {
                case 'qtype_multichoice_multi_question':
                    $answer_order = $question->get_order($question_attempt);
                    for ($i = 0; $i < count($answer_order); $i++) {
                        $answer = $question->answers[$answer_order[$i]];

                        $user_chose_this_answer = $response['choice' . $i] == $answer->id;
                        $user_answer_is_correct = self::is_multichoice_answer_correct($answer) && $user_chose_this_answer;
                        $answer_details[] = [
                            'checked' => $user_chose_this_answer,
                            'user_answer_correct' => $user_answer_is_correct,
                        ];
                    }
                    break;
                case 'qtype_multichoice_single_question':
                    foreach ($question->answers as $key => $answer) {
                        $user_chose_this_answer = $question->get_order($question_attempt)[$response['answer']] == $key;
                        $user_answer_is_correct = self::is_multichoice_answer_correct($answer) && $user_chose_this_answer;
                        $answer_details[] = [
                            'checked' => $user_chose_this_answer,
                            'user_answer_correct' => $user_answer_is_correct,
                        ];
                    }
                    break;
                default:
                    // This should never happen because except multichoice adds some new stuff
                    throw new moodle_exception(
                        'unknown_multichoice_question_type',
                        'mod_adleradaptivity',
                        '',
                        null,
                        'Type of question is not known: ' . get_class($question)
                    );
            }
        }

        return $answer_details;
    }
}
