# Piattaforma corsi 3D WEB LAB

Area corsisti con accesso a codice + pannello admin. PHP 8 + SQLite: niente database
da configurare, niente dipendenze da installare.

## Come è fatta

```
index.php        accesso del cliente con il solo codice
corso.php        elenco delle lezioni per categoria, con avanzamento
lezione.php      player + materiali della lezione
materiali.php    tutti i PDF in un posto solo
media.php        serve video e PDF SOLO a chi ha una sessione valida
progresso.php    salva il punto del video e le lezioni completate

admin/           pannello: categorie, lezioni, materiali, codici
inc/             database, sessioni, funzioni comuni
storage/         video e PDF caricati       ← non raggiungibile dal browser
data/corso.db    il database                ← non raggiungibile dal browser
install.php      controllo iniziale, poi va cancellato
router.php       serve solo all'anteprima locale, NON caricarlo online
```

## Messa online su Hostinger

1. **Carica** tutto il contenuto della cartella dentro `public_html/corsi/`
   (oppure nella radice se la piattaforma sta su un sottodominio dedicato).
   Non caricare `router.php`.

2. **Permessi**: `storage/` e `data/` devono essere scrivibili (755 di solito basta;
   se l'installer segnala problemi, 775).

3. **PHP 8.1 o superiore** dal pannello Hostinger, con `pdo_sqlite` attivo
   (è attivo di serie).

4. Apri **`tuodominio.it/corsi/install.php`**: controlla cartelle e database,
   e ti fa creare l'accesso admin.

5. **Cancella `install.php`** dal server.

6. Vai su `/corsi/admin/` → crea le categorie → carica le lezioni → genera i codici.

## Il flusso dell'ordine

1. Arriva l'ordine sullo store.
2. In `admin/codici.php` generi un codice, ci metti nome cliente e numero d'ordine.
3. Incolli il codice nella mail di conferma ordine di Shopify.
4. Il cliente lo digita su `/corsi/` ed entra. Nessuna registrazione, nessuna password.

Se un codice gira dove non deve, **Revoca**: si chiude subito, anche a sessione aperta.

## I video

Tre modi, si sceglie lezione per lezione:

- **File caricato qui** — il caricamento avviene a blocchi da 4 MB, quindi il limite
  `upload_max_filesize` di Hostinger non conta. I file finiscono in `storage/video/`
  e si servono solo attraverso `media.php` con la sessione valida.
- **Link diretto** a un MP4 esterno.
- **Embed** (Vimeo, Bunny Stream, YouTube non in elenco).

**Nota sull'hosting condiviso.** Hostinger serve bene i file fino a qualche centinaio
di MB. Sopra i ~300 MB per lezione, o con molti corsisti che guardano insieme, la banda
del piano condiviso diventa il collo di bottiglia: in quel caso conviene tenere i video
su Bunny Stream (pochi euro al mese) e qui incollare l'embed. La piattaforma resta
identica, cambia solo dove sta il file.

## Sicurezza

- `storage/`, `data/` e `inc/` sono bloccati da `.htaccess`: dal browser rispondono 403.
- I video passano da `media.php`, che controlla la sessione e supporta le richieste Range
  (senza le quali non ci si potrebbe spostare nella timeline).
- Il download diretto dal player è disattivato e il tasto destro sul video è bloccato.
  Sono deterrenti, non protezione assoluta: chi vuole davvero registrare lo schermo lo fa,
  e nessuna piattaforma al mondo lo impedisce.
- Protezione CSRF su tutti i form, freno dopo 8 tentativi di codice sbagliato,
  password admin con hash bcrypt.

## Backup

Copia `data/corso.db` e la cartella `storage/`. Sono tutti i tuoi dati.
