# About this project

Dieses Moodle Modul bildet das Adaptivitätselement aus der 3D Umgebung in moodle ab.
In Moodle wird keine Adaptivität untertützt, es werden lediglich alle Fragen und Informationen angezeigt
und können vom Nutzer auch bearbeitet werden. Der Zustand zwischen 3D und Moodle ist dabei identisch.

![database diagram](db_diagram.png)


## Kompabilität
Die minimal notwendige Moodle Version ist auf 4.1.0 gesetzt, daher wird die Installation auf älteren Versionen nicht funktionieren.
Prinzipiell sollte dieses Plugin auch auf älteren Versionen funktionieren, dies wird aber nicht getestet und spätestens bei der
Nutzung weiterer AdLer Plugins wird es zu Problemen kommen, da diese Features nutzen, die erst in neueren Moodle Versionen verfügbar sind.

Folgende Versionen werden unterstützt (mit mariadb und postresql getestet):

| Moodle Branch           | PHP Version |
|-------------------------|-------------|
| MOODLE_401_STABLE (LTS) | 8.1         |
| MOODLE_402_STABLE       | 8.1         |

