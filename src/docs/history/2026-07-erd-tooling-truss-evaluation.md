# Αξιολόγηση εργαλείων ERD: `kolydart/laravel` erd:generate vs. Truss

Ημερομηνία: 2026-07-28. Κατάσταση: αξιολόγηση, δεν έχει ληφθεί απόφαση υιοθέτησης.

## Τι συγκρίνεται

| | `erd:generate` (kolydart/laravel) | `albertoarena/laravel-truss` v1.3.0 |
|---|---|---|
| Είδος | Artisan command, παράγει αρχείο | Web UI + Artisan commands |
| Έξοδος | Στατικό Mermaid σε `.md` | Διαδραστικό διάγραμμα στο `/truss` |
| Πηγή σχήματος | Doctrine DBAL introspection | Native Laravel 12 Schema API |
| Εξάρτηση | `doctrine/dbal` (ήδη σε require-dev) | `spatie/laravel-package-tools` |
| Εγκατάσταση εδώ | Ήδη διαθέσιμο μέσω του πακέτου | Εγκαταστάθηκε ως `--dev` |

## Μετρήσεις στη βάση `l_helmarc` (79 πίνακες)

- `erd:generate` → **43 πίνακες** στο διάγραμμα (αφαιρεί system tables, συμπτύσσει junction tables σε σχέσεις many-to-many).
- Truss → **76 πίνακες** (αφαιρεί μόνο τα framework tables: `migrations`, `jobs`, `failed_jobs`).

Η διαφορά είναι ουσιώδης για την αναγνωσιμότητα. Το ίδιο το config του Truss προειδοποιεί για «large schema» πάνω από 60 πίνακες, οπότε το `l_helmarc` βρίσκεται ήδη στη ζώνη όπου το πλήρες διάγραμμα δεν διαβάζεται χωρίς focus/filter.

## Σοβαρό εύρημα: το Truss δεν περιορίζεται στη βάση του project

Στο [`SnapshotBuilder::introspect()`](../../vendor/albertoarena/laravel-truss/src/Introspection/SnapshotBuilder.php) το Truss καλεί `$builder->getTables()` χωρίς όρισμα schema. Στη Laravel 12 αυτό μεταφράζεται σε:

```sql
select ... from information_schema.tables
where table_schema not in ('information_schema','mysql','ndbinfo','performance_schema','sys')
```

δηλαδή **όλες τις βάσεις που βλέπει ο MySQL user**, όχι τη βάση της σύνδεσης.

Σε τοπικό Herd, όπου ένας MySQL server φιλοξενεί όλα τα projects, το αποτέλεσμα ήταν:

- `truss:show` → **1138 πίνακες από 23 schemas** (`l_alumni`, `l_booking`, `l_eeme_apps`, `l_helmarc_test_1..8`, κ.λπ.) αντί για 79.
- Οι ξένοι πίνακες εμφανίζονται με **0 στήλες / 0 foreign keys**, επειδή το `getColumns()` επιλύεται μόνο στο τρέχον schema.
- Πίνακες με ίδιο όνομα σε πολλές βάσεις εμφανίζονται **πολλαπλές φορές**.

### Το workaround μέσω config δεν αρκεί

Το `truss.excluded_tables` φιλτράρει **κατά όνομα**, όχι κατά schema. Επειδή οι βάσεις `l_helmarc_test_1..8` είναι κλώνοι της κύριας, και **τα 79 ονόματα** πινάκων υπάρχουν και σε άλλα schemas, μένουν **870 διπλότυπες εγγραφές** ανεξάρτητα από το τι αποκλείσεις. Το εργαλείο είναι πρακτικά μη λειτουργικό εδώ χωρίς διόρθωση κώδικα.

### Η διόρθωση

Μονογραμμική, με χρήση του επίσημου Laravel API:

```php
// SnapshotBuilder::introspect()
$builder->getTables($builder->getCurrentSchemaListing()),
```

Το `MySqlBuilder::getCurrentSchemaListing()` επιστρέφει `[$connection->getDatabaseName()]`. Δοκιμάστηκε προσωρινά στο `vendor/` και έδωσε **79 πίνακες**, δηλαδή σωστό αποτέλεσμα, ενώ το UI στο `/truss` απέδωσε κανονικά 76 πίνακες μετά τα framework exclusions.

Η δοκιμαστική αλλαγή **έχει αναιρεθεί**: το `vendor/` είναι στην αρχική του κατάσταση και το Truss παραμένει μη λειτουργικό σε αυτό το περιβάλλον. Η διόρθωση καταγράφεται εδώ ως πρόταση προς upstream, όχι ως εφαρμοσμένο patch.

## Θετικά / αρνητικά

### `erd:generate`

**Θετικά**

- Παράγει **artifact σε git**: το `.md` είναι versionable, diffable, εμφανίζεται rendered σε GitHub και IDE.
- Το `--compare` ανιχνεύει schema drift, οπότε μπαίνει σε CI.
- **Σύμπτυξη junction tables** σε `}o--o{`: 43 αντί 76 κόμβοι, σαφώς πιο ευανάγνωστο.
- Σωστά περιορισμένο στη βάση της σύνδεσης (το Doctrine DBAL δέχεται `dbname`).
- Μηδενικό runtime αποτύπωμα: κανένα route, κανένα asset, καμία επιφάνεια ασφαλείας.
- Το κείμενο Mermaid τροφοδοτεί άμεσα LLM context. Το [`docs/features/database-erd-example.md`](../features/database-erd-example.md) είναι ακριβώς αυτό: χειροκίνητα ομαδοποιημένο διάγραμμα με βάση τη γεννήτρια.

**Αρνητικά**

- Απαιτεί `doctrine/dbal`, το οποίο η Laravel εγκατέλειψε από την 11. Είναι επιπλέον εξάρτηση σε τροχιά συντήρησης.
- **Απώλεια τύπων**: το `mapColumnType()` ισοπεδώνει τα πάντα σε `int`/`string`/`decimal`. Δεν ξεχωρίζεις `varchar(255)` από `text`, ούτε `bigint unsigned` από `tinyint`.
- Στατικό: κανένα zoom, pan, filter, focus. Ένα μπλοκ Mermaid 43 πινάκων παραμένει δύσκολο χωρίς χειροκίνητη ομαδοποίηση.
- Καμία εξαγωγή σε PNG/SVG/CSV/JSON.
- Η αναγέννηση είναι χειροκίνητη.

### Truss

**Θετικά**

- **Native Laravel Schema API**, χωρίς Doctrine. Λιγότερες εξαρτήσεις, ευθυγραμμισμένο με το μέλλον του framework.
- **Διαδραστικότητα**: pan, zoom, fit, filter κατά όνομα, focus mode σε έναν πίνακα με ρυθμιζόμενο βάθος γειτόνων. Αυτό είναι που λύνει το πρόβλημα των 76 κόμβων.
- **Διατηρεί τους native τύπους** (`varchar(255)`, `bigint unsigned`) με προαιρετικό toggle σε Laravel-style ετικέτες. Σαφώς ακριβέστερο.
- Επισημαίνει **self-referencing foreign keys** με δικό του badge.
- Self-hosted Mermaid και fonts, χωρίς CDN. Λειτουργεί offline και περνά αυστηρό CSP με `script-src 'self'`.
- Εξαγωγή σε PNG, SVG, JSON, CSV.
- **Read-only και gated**: διαβάζει μόνο δομή, ποτέ δεδομένα. Ενεργό μόνο σε `local` εξ ορισμού, με `viewTruss` gate που αποτυγχάνει κλειστά.
- Cached snapshot με TTL και ρητό `truss:rebuild`.

**Αρνητικά**

- **Το bug scoping σχήματος** παραπάνω. Blocker για κάθε setup τύπου Herd/shared MySQL, και δεν παρακάμπτεται με config.
- **Καμία σύμπτυξη junction tables**: το διάγραμμα δείχνει 76 κόμβους αντί για τις 43 ουσιαστικές οντότητες.
- **Δεν παράγει artifact στο repo**. Δεν υπάρχει τίποτα να κάνεις commit, άρα ούτε ανίχνευση schema drift σε CI, ούτε τεκμηρίωση που ταξιδεύει με τον κώδικα.
- Δεν εξυπηρετεί LLM context: το ζητούμενο εκεί είναι κείμενο δίπλα στον κώδικα, όχι μια σελίδα που πρέπει να ανοίξεις.
- Νέο πακέτο (v1.3.0, 2026-07-27), μικρή επιφάνεια συντήρησης.

## Συμπέρασμα

Τα δύο εργαλεία **δεν ανταγωνίζονται**, εξυπηρετούν διαφορετικές στιγμές:

- Το `erd:generate` παράγει την **τεκμηρίωση**: σταθερό, ομαδοποιημένο, versioned στιγμιότυπο του μοντέλου δεδομένων που ζει στο `docs/`.
- Το Truss είναι εργαλείο **εξερεύνησης**: όταν θέλεις να δεις γρήγορα σε τι συνδέεται ο `items` ή τι ακριβώς τύπο έχει μια στήλη, χωρίς να ανοίξεις SQL client.

### Πρόταση

1. Να παραμείνει το `erd:generate` ως πηγή αλήθειας για την τεκμηρίωση.
2. Το Truss να κρατηθεί **μόνο ως dev dependency** και μόνο εφόσον διορθωθεί το scoping upstream. Να ανοιχτεί issue/PR στο repository του πακέτου με τη μονογραμμική διόρθωση.
3. Μέχρι τότε, να μην θεωρείται λειτουργικό σε αυτό το περιβάλλον: το vendor patch χάνεται σε κάθε `composer install`.
4. Ανεξάρτητα από την απόφαση, αξίζει να μεταφερθεί στο `erd:generate` η **διατήρηση των native τύπων**, που είναι το καθαρά καλύτερο σχεδιαστικά σημείο του Truss.
