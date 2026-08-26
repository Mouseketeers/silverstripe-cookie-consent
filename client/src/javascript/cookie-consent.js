import { cookieConsentService } from './cookie-consent-service';

function initCookieConsent() {

    if (cookieConsentService.isRenderingDisabled()) {
        console.info('[cookie-consent] Rendering disabled - skipping cookie consent setup.');
        return;
    }

    const cookieConsentApi = cookieConsentService.getCookieConsentApi();
    const iframeManagerApi = iframemanager();

    cookieConsentService.init();

    const cookieConsentConfig = {
        guiOptions: cookieConsentService.getGuiOptions(),
        categories: cookieConsentService.getConsentCategories(),
        language: {
            autoDetect: 'document',
            default: cookieConsentService.getDefaultLanguage(),
            translations: cookieConsentService.getCookieConsentTranslations(),
        },
        onFirstConsent: () => {
            cookieConsentService.updateGtagConsent();
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

    const iframeManagerConfig = cookieConsentService.buildIframeManagerConfig();

    cookieConsentService._runBeforeRunCallbacks(cookieConsentConfig);

    cookieConsentApi.run(cookieConsentConfig);
    iframeManagerApi.run(iframeManagerConfig);

    function updateCookieConsentDeclaration() {

        const consentHeaderElement = document.getElementById('cookie-consent__header');

        if (!consentHeaderElement) {
            return;
        }

        const cookie = cookieConsentApi.getCookie();

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
};
document.addEventListener('DOMContentLoaded', initCookieConsent);