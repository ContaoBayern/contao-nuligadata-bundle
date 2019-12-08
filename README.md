# contao-nuligadata-bundle

Abfrage von Spielterminen und -ergebnissen über die nuLiga API um damit Einträge in einem
Contao Kalender zu erzeugen.


## Configuration

In der `parameters.yml` der Contao-Inatallation müssen die Zugangsdaten für die API hinterlegt werden:

```
    nuPortalRSHost: 'https://example-portal.liga.nu'
    nuClientID: '**********'
    nuClientSecret: '**********'
```
