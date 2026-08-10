import { cookieConsentService } from './cookie-consent-service';

async function initCookieConsent() {

    const cookieConsentApi = cookieConsentService.getCookieConsentApi();
    const serverSideConfig = await cookieConsentService.getServerSideConfiguration();
    const isGoogleConsentModeEnabled = serverSideConfig?.isGoogleConsentModeEnabled || false;
    const isConsentRegistrationEnabled = serverSideConfig?.isConsentRegistrationEnabled || false;

    function updateGtagConsent() {
        if (isGoogleConsentModeEnabled) {
            cookieConsentService.updateGtagConsent();
        }
    }

    function registerConsent() {
        if (isConsentRegistrationEnabled) {
            cookieConsentService.registerConsent();
        }
    }



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
            const defaultLanguage = serverSideConfig?.defaultLanguage || 'en';
            const translations = serverSideConfig?.translations || {};
            const sections = translations?.[defaultLanguage]?.preferencesModal?.sections || [];
            const getSectionTitleByCategory = (category) =>
                sections.find((item) => item.linkedCategory === category)?.title || category;
            const acceptedCategoryTitles = (cookie?.categories || [])
                .map((category) => getSectionTitleByCategory(category))
                .filter(Boolean);

            acceptedCategoriesElement.textContent = acceptedCategoryTitles.join(', ') || '';
        }
    }

    if (isGoogleConsentModeEnabled) {
        cookieConsentService.initializeGtagConsent();
    }

    const translations = serverSideConfig?.translations || {};
    const categories = serverSideConfig?.categories || {
        functional: {
            readOnly: true
        }
    };

    const defaultConfig = {
        guiOptions: {
            consentModal: {
                layout: 'box',
                position: 'bottom left',
                equalWeightButtons: true,
                flipButtons: false
            },
            preferencesModal: {
                layout: 'box',
                position: 'right',
                equalWeightButtons: true,
                flipButtons: false
            }
        },
        categories,
        language: {
            autoDetect: 'document',
            default: serverSideConfig?.defaultLanguage || 'en',
            translations
        },
        onFirstConsent: () => {
            updateGtagConsent();
            registerConsent();
        },
        onConsent: () => {
            updateGtagConsent();
        },
        onChange: () => {
            updateGtagConsent();
            registerConsent();
            updateCookieConsentDeclaration();

        }
    };
    cookieConsentApi.run(defaultConfig);
};
document.addEventListener('DOMContentLoaded', initCookieConsent);