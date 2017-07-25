Die Komponente LGitLab nimmt TAG-Ereignisse einer angebundenen GitLab-Umgebung entgegen und erzeugt aus dem zugehörigen Commit eine Einsendung.

| Themen |
| :- |
| [Befehle/Eingänge (Commands.json)](#eingaenge) |
| [Ausgänge (Component.json => Links)](#ausgaenge) |
| [Anbindungen (Component.json => Connector)](#anbindungen) |

## <a name='eingaenge'></a>Befehle/Eingänge (Commands.json)
Diese Befehle bietet diese Komponente als Aufruf an.

||submit|
| :----------- |:----- |
|Beschreibung| Es wird das Format von GitLab als Eingabe erwartet. Der übermittelte Commit wird in dieser Veranstaltung als Einsendung hinterlegt.|
|Befehl| POST /submit/course/:courseid|
|Eingabetyp| -|
|Ausgabetyp| binary|
|||
||Patzhalter|
|Name|courseid|
|Regex|%^([0-9_]+)$%|
|Beschreibung|eine Veranstaltungs ID (`Course`)|

||getSubmitHelp|
| :----------- |:----- |
|Beschreibung| Gibt eine Hilfeseite zu dieser Veranstaltung aus.|
|Befehl| GET /submit/course/:courseid|
|Eingabetyp| -|
|Ausgabetyp| binary|
|||
||Patzhalter|
|Name|courseid|
|Regex|%^([0-9_]+)$%|
|Beschreibung|eine Veranstaltungs ID (`Course`)|

||getEmptySubmitHelp|
| :----------- |:----- |
|Beschreibung| Gibt eine Hilfeseite aus, ohne Veranstaltungsbezug.|
|Befehl| GET /submit/course|
|Eingabetyp| -|
|Ausgabetyp| binary|

||addPlatform|
| :----------- |:----- |
|Beschreibung| installiert die zugehörige Tabelle und die Prozeduren für diese Plattform|
|Befehl| POST /platform|
|Eingabetyp| Platform|
|Ausgabetyp| Platform|

||deletePlatform|
| :----------- |:----- |
|Beschreibung| entfernt die Komponente und ihre installierten Bestandteile aus der Plattform|
|Befehl| DELETE /platform|
|Eingabetyp| -|
|Ausgabetyp| Platform|

||getExistsPlatform|
| :----------- |:----- |
|Beschreibung| prüft, ob die Tabelle und die Prozeduren existieren und die Komponente generell vollständig installiert ist|
|Befehl| GET /link/exists/platform|
|Eingabetyp| -|
|Ausgabetyp| Platform|

||getHelpButton|
| :----------- |:----- |
|Beschreibung| dieser Befehl erzeugt die HTML-Daten der Hilfe-Schaltfläche|
|Befehl| GET /view/button/help|
|Eingabetyp| -|
|Ausgabetyp| binary|


## <a name='ausgaenge'></a>Ausgänge (Component.json => Links)
Wenn eine Komponente selbst noch Unteranfragen an andere Komponenten stellen möchte, dann werden diese über die `Ausgänge` bearbeitet.
Dabei kann ein Ausgang bereits auf eine Komponente gerichtet sein (`Ziel`) oder durch die Zielkomponente selbst angebunden werden (`Connector`)

||getUser|
| :----------- |:----- |
|Ziel| DBUser|
|Befehl| GET /user/user/:userid|
|Beschreibung| zum Abrufen der Daten eines einzelnen Nutzers|

||getGroup|
| :----------- |:----- |
|Ziel| DBGroup|
|Befehl| GET /group/user/:userid/exercisesheet/:sheetid|
|Beschreibung| um die Gruppen einer Übungsserie und eines Nutzers zu ermitteln|

||postSubmission|
| :----------- |:----- |
|Ziel| LProcessor|
|Befehl| POST /submission|
|Beschreibung| zum Erstellen einer Einsendung|

||getCourseExerciseSheets|
| :----------- |:----- |
|Ziel| LExerciseSheet|
|Befehl| GET /exercisesheet/course/:courseid/exercise|
|Beschreibung| um alle Übungsserien einer Veranstaltung abzurufen|


## <a name='anbindungen'></a>Anbindungen (Component.json => Connector)
Eine Anbindung verlangt von einer anderen Komponente (`Ziel`) die Anbindung/Verbindung zu dieser Komponente.
Wenn eine Anbindung den aufzurufenden Befehl vorgibt, dann ist die Notation: METHODE URL (PRIORITÄT).

|Ausgang|request|
| :----------- |:----- |
|Ziel| CLocalObjectRequest|
|Beschreibung| damit LGitLab als lokales Objekt aufgerufen werden kann|

|Ausgang|postPlatform|
| :----------- |:----- |
|Ziel| CInstall|
|Beschreibung| der Installationsassistent soll uns bei der Plattforminstallation aufrufen|

|Ausgang|getContent|
| :----------- |:----- |
|Ziel| UINavigation|
|Beschreibung| wir wollen eine eigene Schaltfläche für unsere Hilfeseite in der Navigationsleiste zeichnen|
|| GET /view/button/help (150)|

|Ausgang|request|
| :----------- |:----- |
|Ziel| CHelp|
|Beschreibung| hier werden Hilfedateien beim zentralen Hilfesystem angemeldet, sodass sie über ihre globale Adresse abgerufen werden können|
|| GET /help/:language/extension/LGitLab/A.png|
|| GET /help/:language/extension/LGitLab/B.png|
|| GET /help/:language/extension/LGitLab/C.png|
|| GET /help/:language/extension/LGitLab/D.png|
|| GET /help/:language/extension/LGitLab/E.png|
|| GET /help/:language/extension/LGitLab/F.png|
|| GET /help/:language/extension/LGitLab/G.png|
|| GET /help/:language/extension/LGitLab/H.png|
|| GET /help/:language/extension/LGitLab/I.png|
|| GET /help/:language/extension/LGitLab/J.png|
|| GET /help/:language/extension/LGitLab/K.png|
|| GET /help/:language/extension/LGitLab/L.png|
|| GET /help/:language/extension/LGitLab/M.png|
|| GET /help/:language/extension/LGitLab/N.png|
|| GET /help/:language/extension/LGitLab/O.png|
|| GET /help/:language/extension/LGitLab/P.png|
|| GET /help/:language/extension/LGitLab/Q.png|
|| GET /help/:language/extension/LGitLab/R.png|
|| GET /help/:language/extension/LGitLab/S.png|
|| GET /help/:language/extension/LGitLab/T.png|
|| GET /help/:language/extension/LGitLab/U.png|


Stand 25.07.2017
