import { cookieConsentService } from './cookie-consent-service';

async function initCookieConsent() {

    const cookieConsentApi = cookieConsentService.getCookieConsentApi();

    const cookieConsentConfig = {
        guiOptions: cookieConsentService.getGuiOptions(),
        categories: cookieConsentService.getConsentCategories(),
        language: {
            autoDetect: 'document',
            default: cookieConsentService.getDefaultLanguage(),
            translations: cookieConsentService.getCookieConsentTranslations(),
        },
        onFirstConsent: () => {
            cookieConsentService.registerConsent();
            updateCookieConsentDeclaration();
        },
        onConsent: () => {
            cookieConsentService.updateGtagConsent();
        },
        onChange: () => {
            cookieConsentService.updateGtagConsent();
            cookieConsentService.registerConsent();
            updateCookieConsentDeclaration();
        }
    };

    cookieConsentService.applyBeforeRunCallbacks(cookieConsentConfig);

    await cookieConsentApi.run(cookieConsentConfig);

    if (!cookieConsentService.isIframeManagerDisabled()) {
        window.iframemanager().run(cookieConsentService.buildIframeManagerConfig());
    }

    cookieConsentService.applyAfterRunCallbacks();
}

function updateCookieConsentDeclaration() {
    const consentHeaderElement = document.getElementById('cookie-consent__header');

    if (!consentHeaderElement) {
        return;
    }

    const cookie = cookieConsentService.getCookieConsentApi().getCookie();

    if (!cookie) {
        return;
    }

    const consentIdElement = document.getElementById('cookie-consent-id');
    const consentTimestampElement = document.getElementById('cookie-consent-timestamp');
    const acceptedCategoriesElement = document.getElementById('cookie-consent-accepted-categories');

    consentHeaderElement.style.display = 'block';

    if (consentIdElement) {
        consentIdElement.textContent = cookie.consentId || '';
    }
    if (consentTimestampElement) {
        consentTimestampElement.textContent = cookie.consentTimestamp || '';
    }

    if (acceptedCategoriesElement) {
        const acceptedCategoryTitles = cookieConsentService.getAcceptedCategoryTitles(cookie);
        acceptedCategoriesElement.textContent = acceptedCategoryTitles.join(', ') || '';
    }
}
document.addEventListener('DOMContentLoaded', initCookieConsent);
