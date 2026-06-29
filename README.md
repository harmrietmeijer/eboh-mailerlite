# EBOH MailerLite

WordPress-plugin voor nieuwsbrief-signup via [MailerLite Connect API](https://developers.mailerlite.com/docs/).

## Installatie

1. Upload de map `eboh-mailerlite/` naar `wp-content/plugins/`.
2. Activeer **EBOH MailerLite** via WP-admin → Plugins.
3. Ga naar **Instellingen → EBOH MailerLite**, vul je MailerLite API-key en (optioneel) standaard groep-ID in.
4. Plaats de shortcode `[eboh_mailerlite_form]` in een pagina, widget of template.

## Shortcode

```text
[eboh_mailerlite_form]
[eboh_mailerlite_form group="123456"]
[eboh_mailerlite_form button="Schrijf me in" title="Blijf op de hoogte"]
```

| Attribuut | Default | Beschrijving |
|---|---|---|
| `group` | (uit instellingen) | MailerLite-groep-ID waar nieuwe inschrijvers in komen. |
| `button` | (uit instellingen) | Knoptekst. |
| `title` | _leeg_ | Optionele h3 boven het formulier. |

## Settings

| Veld | Doel |
|---|---|
| API key | Bearer-token voor de MailerLite Connect API. Te genereren in MailerLite → Integrations → API. |
| Standaard groep-ID | Numeriek; nieuwe subscribers worden hieraan toegevoegd. |
| Knop-tekst | Standaardtekst op de submit-knop. |
| Placeholder e-mailveld | Tekst in het e-mailveld. |
| Succes-bericht | Wordt getoond na een geslaagde inschrijving (HTML toegestaan via `wp_kses_post`). |
| Foutmelding | Wordt getoond bij een fout. |
| AVG-toestemmingstekst | Verplichte checkbox; als deze tekst leeg is, vervalt de checkbox. |

## Hoe het werkt

- Het formulier submit via AJAX (`admin-ajax.php` → `eboh_ml_subscribe`).
- Server-side validatie: nonce, geldig e-mailadres, consent-checkbox, honeypot-veld.
- API-call: `POST https://connect.mailerlite.com/api/subscribers` met Bearer-token.
- HTTP 200/201/202 = success (subscribed, bestaand, of pending double opt-in).

## Styling

De plugin laadt `assets/form.css` met CSS-variables die je in je thema kunt overschrijven:

```css
.eboh-ml-form {
    --eboh-ml-accent: #EA1928;
    --eboh-ml-radius: 0;
    /* ... */
}
```

## Verbinding testen

Op de instellingen-pagina staat een **Test API-koppeling**-knop die een lightweight GET op `/api/groups` doet. Resultaat verschijnt direct als notice.

## Privacy

- Verzamelt enkel het door de gebruiker zelf ingevulde e-mailadres.
- Stuurt dat e-mailadres naar MailerLite met de geconfigureerde API-key.
- Geen tracking, geen cookies.
- Honeypot-veld voorkomt eenvoudige bot-submissions zonder CAPTCHA.

## Licentie

GPL v2 of later.
