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

Abruf von Daten über das `nuliga:apiaccess` Command. 

Beispiel für ein Skript, das in einem cron job regelmäßig aufgerufen wird:

```
#!/bin/bash

CONTAO_ROOT=/pfad/zu/deiner/contao/installation
CONTAO_CONSOLE=$CONTAO_ROOT/vendor/bin/contao-console
 
COMMAND=nuliga:apiaccess

VERBAND='BHV'
SAISON='19/20'
CLUBNR='12345'

$CONTAO_CONSOLE $COMMAND $VERBAND $SAISON $CLUBNR teams
$CONTAO_CONSOLE $COMMAND $VERBAND $SAISON $CLUBNR meetings
$CONTAO_CONSOLE $COMMAND $VERBAND $SAISON $CLUBNR table
```

### Inhaltselemente und Kalender-Events

FIXME
