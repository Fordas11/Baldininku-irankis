# Baldininkų įrankis – plokščių pjovimo užsakymas

Web aplikacija baldų gamybos įmonių klientams: plokščių pjovimo ir briaunavimo užsakymas (vietoj Excel formos
„Pjovimo užsakymo forma“) su tikslia kainos skaičiuokle, plokščių katalogu pagal sandėlio
likučius ir likučių (atraižų) sandėliu, kurį mato tik administratorius.

Parašyta **PHP 8**, todėl ją galima talpinti beveik bet kuriame PHP serveryje (pvz. kartu su OpenCart svetaine).
Jokių išorinių bibliotekų: PHP + PDO SQLite + paprastas JavaScript.

## Paleidimas

```bash
php -S localhost:8080 -t public
```

Atidaryk `http://localhost:8080`. Administravimas – `http://localhost:8080/admin/`.

Administratoriaus slaptažodis:

```bash
php tools/hash-password.php mano-slaptazodis
```

Gautą hash įrašyk į `config.local.php`:

```php
<?php
return ['admin_password_hash' => '$2y$10$...'];
```

### Duomenų bazė

SQLite failas `data/orders.sqlite` sukuriamas automatiškai pirmą kartą atidarius puslapį (`src/bootstrap.php`).
Tuščioje bazėje įrašomi pavyzdiniai duomenys (`src/catalog.php`): 34 plokštės (Woodeco, Pfleiderer, Kronospan,
Swiss Krono, Sonae Arauco, MDF) – tas pats dekoro kodas su keliomis struktūromis ir storiais, pvz. PB0050:
MA / CW / MT / SG / SO, 10–25 mm – ir 21 likutis sandėlyje. Norint pradėti iš naujo, perkelkite ar
pervadinkite `data/orders.sqlite` – bazė bus sukurta iš naujo su pavyzdiniais duomenimis.

Serveryje `public/` turi būti svetainės šaknis (document root); `data/`, `src/` ir `config*.php` – už jos ribų.

## Ką gali klientas

| | |
| --- | --- |
| Užsakovo duomenys | vardas, įmonė, įmonės kodas, telefonas, el. paštas |
| Atsiėmimas ir pakavimas | atsiims pats / pristatyti adresu, grąžinti atraižas, standartinis / be pakavimo / specialus |
| Plokštės pasirinkimas | dekoro kodas (pvz. W1000, su paieška) → paviršiaus struktūra (ST9, ST10…) → storis; siūlomi tik sandėlyje esantys storiai |
| Detalių sąrašas | ilgis (tekstūros kryptis), plotis, kiekis, pastabos, CNC kodas |
| Kainos skaičiuoklė | kaina skaičiuojama iškart pildant: plokštės, likučiai, pjovimas, briaunavimas, pristatymas, PVM; rodoma pjovimo schema |
| Briaunavimas | spustelėjus detalės schemos kraštą jis pažymimas; dialoge kiekvienam iš 4 kraštų (2 ilgiai, 2 pločiai) atskirai parenkama briauna; siūlomos tik tinkančios plokštės storiui |
| Likučiai | klientas likučių nemato: katalogas (`likuciai.php`) ir pasiūlymai formoje rodomi tik prisijungus administratoriui |
| Patogumai | įklijavimas iš Excel (Ctrl+V), importas iš senos .xlsx formos, juodraštis naršyklėje, suvestinė (m², briaunų metrai), brėžinių failai |

Pateikus užsakymą klientas gauna nuorodą į spausdinamą užsakymo lapą (išdėstymas kaip Excel formoje).

## Ką gali įmonės darbuotojas (`/admin/`)

- Užsakymų sąrašas su suma, paieška, būsenos; „Pildo įmonė“ laukai (užsakymo Nr., data, įvykdymo data).
- **Plokštės** (`admin/boards.php`): katalogas – tipas, gamintojas, dekoro kodas, pavadinimas, struktūra, storis,
  lapo matmenys, tekstūra, kaina už lapą, likutis sandėlyje. CSV importas/eksportas
  (`tipas;gamintojas;kodas;pavadinimas;struktura;storis;ilgis;plotis;tekstura;kaina_be_pvm;sandelyje;aktyvi`).
- **Kainos** (`admin/prices.php`): pjovimo €/m, briaunavimo €/m pagal tipą, juostos užlaida, pjūklo plotis,
  kraštų apipjovimas, likučių nuolaida, pristatymas, minimali suma, PVM, medžiagos skaičiavimo būdas.
- Eksportas į CSV tais pačiais stulpeliais kaip Excel lapai **ardis** ir **OM** (Biesse).
- **Likučių sandėlis** (`admin/offcuts.php`): registruoti likutį (plokštė, storis, matmenys, vieta, kiekis),
  redaguoti, keisti būseną. Užsakymo puslapyje yra mygtukai „+ plokštė“ – užpildo formą tos plokštės duomenimis.

### Likučio būsenos

```
Laisvas ──(administratorius priskiria)───▶ Rezervuotas ──(užsakymas „Atiduotas“)──▶ Panaudotas
   ▲                                            │
   └──────────(užsakymas „Atšauktas“)───────────┘
```

Serveris tikrina, kad likutis laisvas, tos pačios plokštės ir storio, detalė telpa neperkreipiant tekstūros
ir bendras detalių plotas neviršija likučio ploto. Klientai likučių nemato: `likuciai.php` ir `api/offcuts.php`
prieinami tik administratoriui, o kliento atsiųstas likučio pasirinkimas ignoruojamas. Administratorius likutį
parenka pildydamas formą arba jau pateikto užsakymo puslapyje (admin → užsakymas → „Pjauti iš likučių“);
tada likutis rezervuojamas, o užsakymo kaina nekeičiama.

## Kaip skaičiuojama kaina

1. Detalės grupuojamos pagal plokštę ir **išdėstomos ant lapų** (giljotininis pjovimas kaip formatiniame pjūkle):
   įskaitomas pjūklo plotis ir kraštų apipjovimas; plokštėse su tekstūra detalės nesukamos, be tekstūros – gali būti pasuktos.
   Išbandomos kelios strategijos ir parenkama ta, kuriai reikia mažiausiai lapų.
2. **Medžiaga**: sunaudotų lapų skaičius × lapo kaina (arba, pasirinkus, detalių plotas + atliekų %).
   Detalės iš **likučio** – mokama už likutį: jo plotas × plokštės m² kaina − nuolaida.
3. **Pjovimas**: pjūvių ilgis iš išdėstymo (su apipjovimu) × €/m.
4. **Briaunavimas**: (krašto ilgis + užlaida) × kiekis × €/m pagal briaunos tipą.
5. Pristatymas, specialus pakavimas, priemoka iki minimalios sumos, PVM.

Kaina skaičiuojama serveryje (`src/pricing.php`) – ta pati ir naršyklėje, ir pateikiant užsakymą; pateikto užsakymo
kaina išsaugoma ir rodoma užsakymo lape. CNC darbai įkainojami atskirai.

> Pavyzdinės plokštės ir įkainiai (`src/catalog.php`, `config.php`) – **ne tikros kainos**. Prieš naudojant
> importuokite tikrą katalogą (CSV) ir suveskite įkainius admin → Kainos.

## Nustatymai (`config.php`)

- `branch` – padalinio pavadinimas, adresas, telefonas.
- `edge_types` – briaunos, kokio storio plokštėms jos tinka, kodai Ardis/OM eksportui ir spalvos.
  Excel formoje: 18 mm → PVC 0,6 / 1 / 2; 25 mm → 2,8; 36 mm → 4,2; papildomai PVC 0,4 ir ABS 0,8 (iki 22 mm). Tarpiniai storiai priskirti artimiausiai grupei – patikslinkite.
- `max_dimension`, `upload`, `statuses`, `notify_email` (laiškas apie naują užsakymą).

## Struktūra

```
config.php            nustatymai
src/bootstrap.php     DB, sesija, pagalbinės funkcijos, puslapio antraštė
src/orders.php        užsakymų tikrinimas ir saugojimas
src/offcuts.php       likučių katalogas, paieška, rezervavimas
src/catalog.php       plokščių katalogas, CSV, kainų nustatymai
src/pricing.php       išdėstymas ant plokščių ir kainos skaičiavimas
src/export.php        CSV eksportas (Ardis, OM)
src/xlsx_import.php   senos Excel formos nuskaitymas
src/view.php          užsakymo lapas (peržiūra / spausdinimas)
public/index.php      kliento forma
public/likuciai.php   likučių katalogas
public/order.php      užsakymo peržiūra pagal slaptą nuorodą
public/api/           submit, import, offcuts, quote (JSON)
public/admin/         administravimas
data/                 SQLite DB ir įkelti failai (ne git)
```
