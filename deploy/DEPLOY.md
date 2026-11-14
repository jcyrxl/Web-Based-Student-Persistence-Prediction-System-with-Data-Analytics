# Paano i-deploy ang system nang libre

Ang system ay **PHP + MySQL** lang. Iisa ang code at iisa ang database — walang Firebase, walang ibang service.
Pumili ng **isa** sa tatlo. Para sa defense, ang pinakaligtas ay **A** (laptop) at gawing backup ang B o C.

| | Gastos | Hirap | Maganda para sa | Ingat |
|---|---|---|---|---|
| **A. Laptop (XAMPP) + public link** | ₱0 | Madali | Defense day (walang aasa sa internet host) | Kailangang bukas ang laptop at may internet para sa link |
| **B. InfinityFree** (libreng PHP + MySQL hosting) | ₱0 | Madali (walang server na aayusin) | Online link na laging bukas para sa demo | 50,000 hits kada araw; kapag lumampas, sinususpinde ang account ng 24 oras |
| **C. Azure for Students VM** | ₱0 (mula sa $100 credit) | Katamtaman (may script) | "Totoong" server; maibabago ang files | Kailangan ng student verification; huwag hayaang maubos ang credit |

> **Bakit hindi Vercel?** Ang Vercel ay para sa Node/static sites. Ang PHP doon ay nagagawa lang sa pamamagitan ng community runtime na tumatakbo bilang *serverless functions* (walang permanenteng file storage, walang sariling MySQL). Ang system natin ay gumagamit ng PHP sessions, nagsusulat ng mga larawan (`uploads/`) at ng `storage/`, at kailangan ng MySQL — kaya kailangang isulat muli ang malaking bahagi. Hindi sulit. Ang "Set up Vercel for me. Fetch …" na prompt ay para sa isang AI coding agent na may access sa Vercel account ninyo; hindi ito kaya ng chat na ito, at hindi rin kailangan.

---

## Una sa lahat: i-export ang database ninyo ngayon (para sa B at C)

Nasa XAMPP na ninyo ang mga account, classes, at grades. Dalhin ang lahat ng iyon:

1. Buksan ang `http://localhost/phpmyadmin` → piliin ang `ccs_attrition_system` → **Export**.
2. **Custom** → alisin ang check ng **"Add CREATE DATABASE / USE statement"** (hindi ito pinapayagan ng libreng hosting) → **Export**.
3. I-save bilang **`my_database.sql`**.

Kung walang export (bagong install), gamitin ang `database/hosting/1_schema_hosting.sql` at pagkatapos ang `2_dummy_data.sql`, tapos buksan ang `create_admin.php` para gumawa ng unang Admin.

---

## A. Laptop + public link (pinakaligtas sa defense)

1. Gamitin ang XAMPP na gumagana na. Siguraduhing `http://localhost/attrition-system/` ay bukas.
2. Para may link ang panel o mga kaklase: i-install ang **Cloudflare Tunnel** (`cloudflared`) at patakbuhin:
   `cloudflared tunnel --url http://localhost`
   May lalabas na `https://….trycloudflare.com` na link na diretso sa laptop ninyo. (Kung ayaw, puwede ang `ngrok http 80`.)
3. Idagdag ang `/attrition-system/` sa dulo ng link kung nasa subfolder ang system.
4. Patayin ang tunnel pagkatapos ng defense.

---

## B. InfinityFree (libreng PHP + MySQL)

1. Mag-sign up sa **infinityfree.com** → *Create Account* → pumili ng libreng subdomain (halimbawa `ccs-attrition.rf.gd`).
2. Sa control panel → **MySQL Databases** → gumawa ng database. Isulat ang **host, database name, user, password**.
3. **phpMyAdmin** (mula sa control panel) → piliin ang database → **Import** → `my_database.sql`
   (o `1_schema_hosting.sql` at saka `2_dummy_data.sql`).
4. I-upload ang **laman ng folder ng system** sa loob ng `htdocs/` gamit ang File Manager o FileZilla (FTP).
5. Sa `config/` kopyahin ang `db.local.example.php` → pangalanan `db.local.php` → ilagay ang detalye mula sa hakbang 2.
6. Buksan ang `https://subdomain-ninyo/check_install.php`. Dapat berde ang lahat. (Piliin ang PHP 7.4 o 8.x sa control panel kung may pula sa PHP.)
7. Kapag berde na, **burahin ang `check_install.php` at `create_admin.php`** sa server.

Tandaan: bawal mag-send ng e-mail ang libreng host, pero ayos lang — ipinapakita ng system ang 6-digit code sa screen (demo mode).

---

## C. Azure for Students VM (iisang makina para sa system at database)

1. Pumunta sa **azure.microsoft.com/free/students** → mag-sign in gamit ang **school e-mail** (kailangang kilalanin ng Microsoft ang school ninyo; kung hindi, subukan ang ibang e-mail ng school o ang GitHub Student Developer Pack).
2. **Create a resource → Virtual machine**: *Ubuntu Server 24.04 LTS*, pinakamaliit na size (halimbawa **B1s**), password o SSH key. Sa **Networking** tab, payagan ang **HTTP (80)** at SSH (22).
3. Ilipat ang folder ng system sa VM (unzip muna sa laptop):
   `scp -r attrition-system azureuser@IP-NG-VM:~/`
   (o gamit ang FileZilla/SFTP). Kung may `my_database.sql`, ilagay ito sa loob ng folder bago ilipat.
4. Mag-SSH: `ssh azureuser@IP-NG-VM`, tapos:
   `cd attrition-system && sudo bash deploy/setup_ubuntu.sh`
   Ini-install nito ang Apache, PHP, at MariaDB, ginagawa ang database, ini-import ang data, at inilalagay ang system online.
5. Buksan ang link na ipapakita ng script (`http://IP/attrition/check_install.php`, tapos `/attrition/`).
6. **Burahin ang `check_install.php` at `create_admin.php`** (nasa `/var/www/html/attrition/`).

Para magbago ng files pagkatapos: i-edit sa SFTP o `sudo nano /var/www/html/attrition/registrar/…`. Para makatipid ng credit, i-**Stop (deallocate)** ang VM kapag hindi ginagamit. Dahil HTTP lang ito (walang SSL), gamitin lang para sa demo gamit ang dummy data.

---

## Bago ang defense (checklist)
- [ ] Naka-export ang `my_database.sql` at naka-save sa dalawang lugar.
- [ ] Gumagana ang login ng Admin, Program Head, at Faculty sa link na ibibigay.
- [ ] Nabura ang `create_admin.php` at `check_install.php` sa live server.
- [ ] Palitan ang default na password ng mga demo account bago ibigay ang link sa iba.
- [ ] May backup na XAMPP sa laptop (A) kung bumagsak ang internet o ang host.
