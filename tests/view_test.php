<?php

namespace mod_adleradaptivity\local\output\pages;

use mod_adleradaptivity\lib\adler_testcase;
use ReflectionClass;

global $CFG;
require_once($CFG->dirroot . '/mod/adleradaptivity/tests/lib/adler_testcase.php');

class view_test extends adler_testcase {
    public function test_sort_questions_in_tasks_by_difficulty() {
        $tasks = [
            1 => [
                'title' => 'Task 1',
                'questions' => [
                    [
                        'difficulty' => 0
                    ],
                    [
                        'difficulty' => 100
                    ]
                ]
            ],
            2 => [
                'title' => 'Task 2',
                'questions' => [
                    [
                        'difficulty' => 200
                    ],
                    [
                        'difficulty' => 100
                    ]
                ]
            ]
        ];

        // Make the method to test accessible
        $reflection = new ReflectionClass(view_page::class);
        $method = $reflection->getMethod('sort_questions_in_tasks_by_difficulty');
        $method->setAccessible(true);
        // Invoke the method and store the result
        $sorted_tasks = $method->invoke(null, $tasks);

        // verify response
        $this->assertEquals(0, $sorted_tasks[1]['questions'][0]['difficulty'], 'Not sorted correctly');
        $this->assertEquals(100, $sorted_tasks[1]['questions'][1]['difficulty'], 'Not sorted correctly');
        $this->assertEquals(100, $sorted_tasks[2]['questions'][0]['difficulty'], 'Not sorted correctly');
        $this->assertEquals(200, $sorted_tasks[2]['questions'][1]['difficulty'], 'Not sorted correctly');

        // verify that the original array was not modified
        $this->assertEquals(0, $tasks[1]['questions'][0]['difficulty'], 'Original array was modified');
        $this->assertEquals(100, $tasks[1]['questions'][1]['difficulty'], 'Original array was modified');
        $this->assertEquals(200, $tasks[2]['questions'][0]['difficulty'], 'Original array was modified');
        $this->assertEquals(100, $tasks[2]['questions'][1]['difficulty'], 'Original array was modified');
    }
}