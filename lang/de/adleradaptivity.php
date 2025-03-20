<?php
/**
 * Languages configuration for the mod_adleradaptivity plugin.
 *
 * @package   mod_adleradaptivity
 * @copyright 2023, Markus Heck <markus.heck@hs-kempten.de>
 */

$string['pluginname'] = 'Adler Adaptivitätselement';
$string['modulename'] = 'Adler Adaptivitätselement';
$string['modulenameplural'] = 'Adler Adaptivitätselemente';
$string['modulename_help'] = 'Fügen Sie hier den Hilfetext für den Modulnamen ein.';
$string['pluginadministration'] = 'Adler Adaptivitätselement Administration';

$string['adleradaptivity:addinstance'] = 'Ein neues Adler Adaptivitätselement hinzufügen';
$string['adleradaptivity:view'] = 'Adler Adaptivitätselement ansehen';
$string['adleradaptivity:edit'] = 'Adler Adaptivitätselement bearbeiten';
$string['adleradaptivity:create_and_edit_own_attempt'] = 'Eigenen Versuch eines Adler Adaptivitätselements erstellen und bearbeiten';
$string['adleradaptivity:view_and_edit_all_attempts'] = 'Alle attempts aller nutzer bearbeiten (Gefährlich! Es ist nicht nachvollziehbar dass ein Nutzer mit dieser Berechtigung einen Attempt eines anderen Nutzers bearbeitet hat.)';

$string['editing_not_supported'] = 'Bearbeiten von Adler Adaptivitätselementen ist nicht unterstützt. Änderungen Ausschließlich über das Authorentool vornehmen.';
$string['rule_description_default_rule'] = 'Adaptivitätselement abschließen';


$string['view_module_completed_success'] = 'Dieses Modul wurde abgeschlossen.';
$string['view_module_completed_no'] = 'Dieses Modul wurde noch nicht abgeschlossen.';
$string['view_task_title'] = 'Aufgabe';
$string['view_task_status_correct'] = 'Diese Aufgabe wurde abgeschlossen.';
$string['view_task_status_optional_not_attempted'] = 'Diese Aufgabe wurde noch nicht versucht, ist aber optional.';
$string['view_task_status_optional_incorrect'] = 'Diese Aufgabe wurde falsch beantwortet, ist aber optional.';
$string['view_task_status_incorrect'] = 'Diese Aufgabe wurde noch nicht abgeschlossen.';
$string['view_task_status_not_attempted'] = 'Diese Aufgabe wurde noch nicht versucht.';
$string['view_task_optional'] = 'Diese Aufgabe ist nicht erforderlich, um das Modul abzuschließen.';
$string['view_task_required_difficulty'] = 'Mindestschwierigkeitsgrad, um die Aufgabe abzuschließen';
$string['view_question_success'] = 'Diese Frage wurde mindestens einmal richtig beantwortet.';

$string['difficulty_0'] = 'Leicht';
$string['difficulty_100'] = 'Mittel';
$string['difficulty_200'] = 'Schwer';

$string['privacy:metadata:core_question'] = 'Speichert question usage Informationen im core_question-Subsystem.';
$string['privacy:metadata:adleradaptivity_attempts'] = 'Verknüpft Benutzer mit ihren Versuchen von AdLer-Adaptivitätselementen.';
$string['privacy:metadata:adleradaptivity_attempts:user_id'] = 'Die ID des Benutzers, der den Versuch gemacht hat.';
$string['privacy:metadata:adleradaptivity_attempts:attempt_id'] = 'Die ID des Versuchs.';
$string['privacy:export:attempt'] = 'Versuch';
$string['privacy:export:attempt:completed'] = 'Abgeschlossen';
$string['privacy:export:attempt:not_completed'] = 'Nicht abgeschlossen';
