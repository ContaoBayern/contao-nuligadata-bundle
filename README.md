# contao-nuligadata-bundle

Abfrage von Spielterminen und -ergebnissen über die nuLiga API um damit (u.a.) Einträge in einem
Contao Kalender zu erzeugen.


## Konfiguration

In der `parameters.yml` der Contao-Inatallation müssen die Zugangsdaten für die API hinterlegt werden:

```
    nuPortalRSHost: 'https://example-portal.liga.nu'
    nuClientID: '**********'
    nuClientSecret: '**********'
```


## Verwendung


### Datenabruf

Abruf von Daten auss der nuLiga-API über das `nuliga:apiaccess` Command. 

Beispiel für ein Skript, das in einem cron job regelmäßig aufgerufen wird:

```
#!/bin/bash

CONTAO_ROOT=/pfad/zu/deiner/contao/installation
CONTAO_CONSOLE=$CONTAO_ROOT/vendor/bin/contao-console
 
COMMAND=nuliga:apiaccess

VERBAND='BHV'
SAISON='19/20'
CLUBNR='12345'

# die verschiedenen Bereiche einzeln synchronisieren
# $CONTAO_CONSOLE $COMMAND $VERBAND $SAISON $CLUBNR teams
# $CONTAO_CONSOLE $COMMAND $VERBAND $SAISON $CLUBNR meetings
# $CONTAO_CONSOLE $COMMAND $VERBAND $SAISON $CLUBNR table

# alle Daten synchronisieren
$CONTAO_CONSOLE $COMMAND $VERBAND $SAISON $CLUBNR all
```

Nach dem ersten API-Aufruf der Mannschaften (Teams) der angegebenen Saison muss in den
Einstellungen jedes Teams der Kalender angegeben werden, in dem die Spiele dieses
Teams bei folgenden API-Aufrufen als Event eingetragen werden sollen. Die Daten zur aktuellen 
Tabelle werden direkt im Team gespeichert. 


### Inhaltselemente und Kalender-Events


#### Content Element Ergebnistabelle

Das Content Element Ergebnistabelle stellt die Tabelle zu einer ausgewählten Mannschaft 
(siehe Backend Modul "Teams") dar, die aus der nuLiga API geholt wurde. Es wird immer die 
aktuellste Tabelle gespeichert und dargestellt. Die Daten der Tabelle werden im Team 
(`tl_team.json_data`) gespeichert. Die Ausgabe kann über das Template 
`ce_nuligadata_table.html5` angepasst werden.


#### Calendar Events

Die Zuordnung von Spielen einer Mannschaft zu einem Kalender erfolgt über die Einstellung im Team. 
Hier muss ein Kalender ausgewählt sein, damit Spiel-Termine in diesem Kalender gespeichert werden.
 
Bei Events, die Daten zu Spielen enthalten, werden die speziellen Felder der Palette "nuLiga"
gefüllt. Diese Daten können im (Kalender- oder Detail-) Template verwendet werden.
Tipp: die zur Verfügung stehenden Variablen können durch Einfügen von `<?php $this->dumpTemplateVars(); ?>`
in das Tempalte eingesehen werden.
