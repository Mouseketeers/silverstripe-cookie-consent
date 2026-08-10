# Cookie Consent Module

This module ships with a minimal default CookieConsent setup and server-side translations.

## What the module injects

The module always injects runtime data into the page:

- `window.gariaCookieConsentConfig`
- `window.gariaCookieConsentConfig.language.translations`

Translations are built on the server from the currently active SilverStripe locale.

## Mode 1: Default minimal setup

By default, the module loads its own JS and CSS and runs CookieConsent with a small baseline config.

Baseline includes:

- Simple `guiOptions` for consent and preferences modal
- Basic categories: `necessary`, `analytics`, `advertisement`, `functionality`
- `language.translations` from server payload

## Mode 2: Custom bootstrap (override only the init step)

If you want to keep module defaults/translations but control when and how the dialog starts, disable auto-init and call the global handler yourself.

### 1) Disable auto-init

Add this before the module script runs:

```html
<script>window.gariaCookieConsentAutoInit = false;</script>
```

### 2) Call the global handler with custom config

```javascript
document.addEventListener('DOMContentLoaded', () => {
  const myCustomConfig = {
    guiOptions: {
      consentModal: {
        position: 'bottom center'
      }
    }
  };

  window.handleCookieConsentDialog(myCustomConfig);
});
```

The module still injects translations and base configuration. Your config is merged on top.

## Mode 3: Bring your own JS

If you want full control, disable the module JS and initialize CookieConsent yourself.

### 1) Disable module JS

Example in your project YAML:

```yml
CookieConsent:
  disable_default_js: true
```

You can also disable module CSS if you want to provide your own styles:

```yml
CookieConsent:
  disable_default_css: true
```

### 2) Initialize CookieConsent in your own script

```javascript
import * as CookieConsent from 'vanilla-cookieconsent';

function getDefaultLanguage(translations) {
  const htmlLang = (document.documentElement.getAttribute('lang') || '').toLowerCase();
  const languageCode = htmlLang.split(/[-_]/)[0];

  if (translations && translations[languageCode]) {
    return languageCode;
  }

  const available = Object.keys(translations || {});
  return available.length ? available[0] : 'en';
}

document.addEventListener('DOMContentLoaded', () => {
  const runtime = window.gariaCookieConsentConfig || {};
  const translations = runtime.language && runtime.language.translations
    ? runtime.language.translations
    : {};

  CookieConsent.run({
    guiOptions: {
      consentModal: {
        layout: 'box',
        position: 'bottom left'
      }
    },
    categories: {
      functional: { readOnly: true },
      analytics: {}
    },
    language: {
      default: getDefaultLanguage(translations),
      autoDetect: 'browser',
      translations: translations
    }
  });
});
```

## Load order

If you use custom bootstrap or BYO JS mode, make sure your custom script runs after:

- `window.gariaCookieConsentConfig` is injected by SilverStripe
- module JS is loaded (for `window.handleCookieConsentDialog`) or `vanilla-cookieconsent` is loaded (for BYO JS)
