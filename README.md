# About this project

![database diagram](db_diagram.png)

some notes for me:

get actual question: 
- get lines FROM question_version WHERE question_versions.questionbankentryid = question_bank_entries.id
- get highest question_versions.version
- from this entry take questionid
- (optional) get question by questionid


# Bugs
- null als answers wenn erste antwort falsch ist bei single choice. \
Backup: /home/markus/2023-10-08_23-59-45_moodle_backup.tar.zst \
request: answer_question, 978c2fb5-a947-4d22-8481-5824187d4641, module_id 5, [false, true]

- möglicher fehler: nach einspielen eines backups sagt answer_questions: exception: multiple found
  
- wrong naming: adleradaptivity_tasks_id -> adleradaptivity_task_id 