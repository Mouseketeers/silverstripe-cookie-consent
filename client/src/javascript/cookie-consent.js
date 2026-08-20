import { cookieConsentService } from './cookie-consent-service';

function initCookieConsent() {

    const cookieConsentApi = cookieConsentService.getCookieConsentApi();
    const iframeManagerApi = iframemanager();

    cookieConsentService.init();

    // const guiOptions = {
    //     consentModal: {
    //         layout: 'box',
    //         position: 'bottom left',
    //         equalWeightButtons: true,
    //         flipButtons: false
    //     },
    //     preferencesModal: {
    //         layout: 'box',
    //         position: 'right',
    //         equalWeightButtons: true,
    //         flipButtons: false
    //     }
    // };

    const guiOptions = {};

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

    cookieConsentApi.run(cookieConsentConfig);
    iframeManagerApi.run(iframeManagerConfig);

    function updateCookieConsentDeclaration() {

        const cookie = cookieConsentApi.getCookie();
        const consentIdElement = document.getElementById('cookie-consent-id');
        const consentTimestampElement = document.getElementById('cookie-consent-timestamp');
        const acceptedCategoriesElement = document.getElementById('cookie-consent-accepted-categories');

        if (consentIdElement) {
            consentIdElement.textContent = cookie?.consentId || '';
        }
        if (consentTimestampElement) {
            consentTimestampElement.textContent = cookie?.consentTimestamp || '';
        }

        if (acceptedCategoriesElement) {
            const acceptedCategoryTitles = cookieConsentService.getAcceptedCategoryTitles(cookie);
            acceptedCategoriesElement.textContent = acceptedCategoryTitles.join(', ') || '';
        }
    }
};
document.addEventListener('DOMContentLoaded', initCookieConsent);