import { cookieConsentService } from './cookie-consent-service';

async function initCookieConsent() {

    updateCookieConsentDeclaration();

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

    cookieConsentService.emit('beforeRun', cookieConsentConfig);

    await cookieConsentApi.run(cookieConsentConfig);

    if (!cookieConsentService.isIframeManagerDisabled()) {
        window.iframemanager().run(cookieConsentService.buildIframeManagerConfig());
    }

    updateCookieConsentDeclaration();

    cookieConsentService.emit('afterRun');
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

    const preferences = cookieConsentService.getCookieConsentApi().getUserPreferences();

    if (!preferences) {
        return;
    }

    consentHeaderElement.style.display = 'block';

    updateCookieConsentRow('cookie-consent-row-id', 'cookie-consent-id', cookie.consentId || '');
    updateCookieConsentRow('cookie-consent-row-timestamp', 'cookie-consent-timestamp', cookie.consentTimestamp || '');
    updateCookieConsentRow(
        'cookie-consent-row-accepted-categories',
        'cookie-consent-accepted-categories',
        cookieConsentService.getAcceptedCategoryTitles(cookie).join(', ')
    );
    updateCookieConsentRow(
        'cookie-consent-row-rejected-categories',
        'cookie-consent-rejected-categories',
        cookieConsentService.getRejectedCategoryTitles(cookie).join(', ')
    );    
    updateCookieConsentRow(
        'cookie-consent-row-accepted-services',
        'cookie-consent-accepted-services',
        cookieConsentService.getAcceptedServiceLabels(preferences).join(', ')
    );
    updateCookieConsentRow(
        'cookie-consent-row-rejected-services',
        'cookie-consent-rejected-services',
        cookieConsentService.getRejectedServiceLabels(preferences).join(', ')
    );
}

function updateCookieConsentRow(rowId, valueId, value) {
    const rowElement = document.getElementById(rowId);
    const valueElement = document.getElementById(valueId);

    if (!rowElement || !valueElement) {
        return;
    }

    if (value === '') {
        rowElement.style.display = 'none';
        return;
    }

    rowElement.style.display = '';
    valueElement.textContent = value;
}
document.addEventListener('DOMContentLoaded', initCookieConsent);
