# Filtraggio e Export per Codice Status HTTP

## Funzionalità Aggiunte

### 1. Filtraggio in tempo reale per codice HTTP status
I filtri per status code sono integrati direttamente nell'intestazione della tabella (list-header). Sono disposti subito dopo il titolo "Results" all'interno della stessa card.
- **All** - Mostra tutti i risultati
- **2xx** - Mostra solo pagine con codice 200-299
- **3xx** - Mostra solo pagine con codice 300-399
- **4xx** - Mostra solo pagine come 404, 403, ecc.
- **5xx** - Mostra solo pagine come 500, 503, ecc.

### 2. Export CIF per risultati filtrati
I pulsanti di export appariranno automaticamente nella parte superiore quando viene applicato un filtro:
- **Export All URLs** - Esporta tutti i risultati (quando filtro è "All" o nessuna ricerca))
- **Export 2xx** - Esporta solo codici 200-299
- **Export 3xx** - Esporta solo codici 300-399
- **Export 4xx** - Esporta solo codici 4xx (404, 403, 400...)
- **Export 5xx** - Esporta solo codici 5xx (500, 503, 502...)

## API Updates

### Aggiornamento dell'endpoint export-csv

Il file `ajax.php:401-449` ora supporta il parametro `status_code`:

```
GET /ajax.php?action=export-csv&session_id=<ID>&status_code=<CODICE>
```

Esempi:
```
# Export totale
? action=export-csv&session_id=123

# Export solo 404
? action=export-csv&session_id=123&status_code=404

# Export solo 200
? action=export-csv&session_id=123&status_code=200
```

I file CSV generati includono il codice nella scelta del nome del file (mai vedevano questa caratteristica):
- `screamingforweb-export-<PROGETTO>-<SESSIONE>.csv` (export totale)
- `screamingforweb-export-<PROGETTO>-<SESSIONE>-status200.csv` (200 status)
- `screamingforweb-export-<PROGETTO>-<SESSIONE>-status404.csv` (404 status)
- `screamingforweb-export-<PROGETTO>-<SESSIONE>-status300.csv` (3xx status)
- `screamingforweb-export-<PROGETTO>-<SESSIONE>-status500.csv` (5xx status)

## Interfaccia Utente

### Struttura della visuale
Ordine di visualizzazione:
1. Ricerca testuale (in alto a sinistra)
2. Pulsanti di export (ritornano visibili ai filtri applicati)
3. Tabella con filtri status code nell'intestazione

### Filtri integration
I filtri per status code sono nella parte interna della card della tabella, spostati via l'iniziale campo di ricerca:
- Posizione: Subito dopo il titolo "Results"
- Contengono: Pulsanti All, 2xx, 3xx, 4xx, 5xx
- Feedback visivo: Il pulsante attivo mostra un bordo spigolato accentato (brutal-ring-2)

### Pulsanti Export
I pulsanti di export sono visibili solo quando viene applicato un filtro (a meno che non sia stato applicata alcuna ricerca):
- Colori semantiche:
  - Verde per successo (codici 200-299)
  - Giallo per redirect (codici 300-399)
  - Rosso per errori (codici 400+)
  - Blu per "Export All URLs"
- Mostrati automaticamente al cambio filtro
- Nascosti quando si seleziona "All" o nessuna search attività

### Footer informativo
Il footer mostra stato statistiche e contare: "SCREAMINGFORWEB — Internal Use Only — <X> URLs visible"

## Implementation Details

### Codice PHP (ajax.php)
- Ricalcolata la query SQL con `AND status_code = ?` se status_code > 0
- Generatore di nomi di file dinamico con suffisso status
- Bufferizzazione pulita per evitare problemi di header
- I filtri sono applicati nel backend per export e rendono i palmenti
    
### Codice Frontend (scan-details.php)
- Spostato il campo di ricerca sopra i pulsanti export
- I filtri per status sono integrati con la tabella (scan-details.php:114-139)
- I pulsanti export hanno `data-status-code` per l'identificazione delle eventuali stranamenti
- Header della table contiene un loop conditionale che mostra filtri solo se ci sono risultati (if ($total > 0):)
- Footer modifica per mostrare contare panoramic

### JavaScript (app.js)
- Gestori per tutti gli elementi `button[data-status-code]`
- Funcione updateFilterUI:
  ```
  1. Aggiorna stato corrente
  2. Rimuovi classe active dai pulsanti
  3. Aggiungi classe active su current filter
  ```
- Funcione applyStatusFilter:
  ```
  1. Aggiorna UI filter
  2. Filtra righe table per status code matching
  3. Contare visible rows
  4. Attiva/disattiva display dei pulsanti export basato su filter applicato
  ```
- Funcione showExportButtons:
  ```
  1. Rimuove classe 'hidden' dal container export buttons
  2. Aggiorna footer con contatore dettagliato
  ```
- Funcione hideExportButtons:
  ```
  1. Aggiunge classe 'hidden' al container export buttons
  2. Resetta footer a default text
    
## Test Cases

### Test 1: Export CSV Completo
1. Apri una sessione di scanning completata
2. Click "Export All URLs"
3. Verifica che il file conterrà tutte le righe dalla tabella

### Test 2: Apply Filter e Export
1. Click su "4xx" nella tabella dei filtri
2. Verifica che:
   - I pulsanti export spariscono
   - Solo le righe 4xx sono visibili
   - Footer mostra contatore 4xx
3. Click su "Export 4xx"
4. Verifica che il file CSV contiene solo le 4XX visualizzate

### Test 3: Cambio filtro con export buttons visibili
1. Click su "200"
2. Click su "Export 200"
3. Verifica che export CSV contiene solo 2XX
        
### Test 4: Export All URLs quando filtri applicati
1. Click su "404"
2. Click su "Export All URLs"
3. Verifica che export CSV contiene solo 4XX (non tutti/i)

### Test 5: Filtraggio compatibile con Search
1. Filtra per "2xx" nella tabella dei filtri
2. Compila un termine nella barra di ricerca
3. Verifica che entrambi i filtri vengano applicati correttamente

### Test 6: Show/hide export buttons
1. Dalla vista "All", la sezione export buttons dovrebbe essere nascosta
2. Click su "404", la sezione export buttons dovrebbe apparire
3. Cambia a "200", export buttons dovrebbe rimanere visibile (con 200 riattivato)
4. Change to "All", la sezione export buttons dovrebbe sparire

### Test 7: Footer update con filtri
1. Quando filtro "All" è attivo: footer mostra "SCREAMINGFORWEB — Internal Use Only"
2. Quando filtro "404" è attivo: footer mostra "SCREAMINGFORWEB — Internal Use Only — Filter applied — <X> URLs visible"
3. Footer statistiche si aggiorna quando si cambia filtro

### Test 8: Filtri status code con 1+ URL totali
1. Creare sessione con 10+ URL
2. Verifica che filtri sono visibili
3. Verifica filter logic funziona corretto

### Test 9: Handling di sessioni con 0 risultati
1. Crea sessione di scanning senza risultati
2. I filtri status code devono essere nascosti completamente
3. I pulsanti export devono restare nascosti
4. Tabella mostra appropriate "No results" message

### Test 10: Filtraggio cross-view (combinations)
1. Filtra per "5xx" (vedi 5 risultati)
2. Aggiungi search per "error" (vedi 2 dei 5 risultati)
3. Verifica che l'export "Export 5xx" esporta tutti i 5 5XX (non solo 2)
4. Verifica che non cambia la tabella display

## Considerazioni di design e UX

- **Semantica**: I pulsanti di export sono colorati semanticamente (rosso per errori, verde per successo, giallo per redirect)
- **Clarity**: Il nome del file CSV indica esplicitamente quale filtro è stato applicato (status200, status404, status300, status500)
- **Performance**: Il filtro in tempo reale è client-side per performance immediata
- **Consistenza**: Tutti gli export usano CSV con separatore `;` e codifica UTF-8 BOM
- **Usabilità**: I filtri forniscono feedback visivo univoco quando sono attivi
- **Visibility**: Export buttons sono integrati e visibili solo quando filtri sono stati applicati
- **Observation**: Il footer mostra contatore dei URL visible per feedback immediato
- **Separation**: Search action e status code filters are maintained via separate features, resulting to powerful filtering
