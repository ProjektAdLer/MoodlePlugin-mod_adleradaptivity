# About this project

![database diagram](db_diagram.png)

some notes for me:

get actual question: 
- get lines FROM question_version WHERE question_versions.questionbankentryid = question_bank_entries.id
- get highest question_versions.version
- from this entry take questionid
- (optional) get question by questionid

 