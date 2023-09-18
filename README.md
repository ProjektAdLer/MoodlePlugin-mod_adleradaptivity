# About this project
This is a sample project to provide a minimalistic module demonstration a working version of the following moodle functionalities:
- an activity module
- implementation of question_bank/engine 
  - (obviously) with questions
  - displaying questions
  - showing the "question bank" tab in module settings
  - processing question attempts (individual questions and the whole "quiz")
- implementation of the completion API
  - with a custom completion rule

This implementation follows the latest approaches (i think latest changes are from 4.1, this was done based on 4.2.1 release in september 2023).
It aims on providing a minimalistic, "just working" example that can be used as reference.
It does not provide a great usability and code quality. It also does not implement "modern" html generation (with templates and so on).

# How to use
1) install plugin
2) create a course
3) add the activity module to the course
4) enable in the activity module settings "Activity completion" -> "Adaptivity rule"
5) add some questions to the question bank
6) view/try the activity module

It will now list just all added questions. My aim was to work with multiplechoice questions, but it should also work with other question types.
The questions are numbered 1a, 1b, 2a, 2b, ... as a test for custom question numbering (Default is just 1, 2, 3, ...).


