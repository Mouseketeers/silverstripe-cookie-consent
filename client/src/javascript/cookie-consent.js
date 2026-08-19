import { cookieConsentService } from './cookie-consent-service';

function initCookieConsent() {

    const cookieConsentApi = cookieConsentService.getCookieConsentApi();
    const iframeManagerApi = iframemanager();
    const serverSideConfig = cookieConsentService.getServerSideConfiguration();
    const defaultLanguage = serverSideConfig?.defaultLanguage || 'en';
    const isGoogleConsentModeEnabled = serverSideConfig?.isGoogleConsentModeEnabled || false;
    const isConsentRegistrationEnabled = serverSideConfig?.isConsentRegistrationEnabled || false;


    if (isGoogleConsentModeEnabled) {
        cookieConsentService.initializeGtagConsent();
    }

    const categories = cookieConsentService.getCategories();

    const cookieConsentConfig = {
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
            translations: serverSideConfig?.translations || {},
        },
        onFirstConsent: () => {
            updateGtagConsent();
            registerConsent();
            updateCookieConsentDeclaration();
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


    const externalMediaServices = cookieConsentService.getExternalMediaServices();

    const iframeManagerConfig = {
        currLang: serverSideConfig?.defaultLanguage || 'en',
        services: externalMediaServices,
        onChange: ({ changedServices, eventSource }) => {
            if (eventSource.type === 'click') {
                const servicesToAccept = [
                    ...cookieConsentApi.getUserPreferences().acceptedServices['embeds'],
                    ...changedServices
                ];
                cookieConsentApi.acceptService(servicesToAccept, 'embeds');
            }
        }
    }
    
    cookieConsentApi.run(cookieConsentConfig);    
    iframeManagerApi.run(iframeManagerConfig);

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
        const translations = serverSideConfig?.translations || {};

        if (consentIdElement) {
            consentIdElement.textContent = cookie?.consentId || '';
        }
        if (consentTimestampElement) {
            consentTimestampElement.textContent = cookie?.consentTimestamp || '';
        }

        if (acceptedCategoriesElement) {

            const sections = serverSideConfig?.translations?.[defaultLanguage]?.preferencesModal?.sections || [];
            const getSectionTitleByCategory = (category) =>
                sections.find((item) => item.linkedCategory === category)?.title;
            const acceptedCategoryTitles = (cookie?.categories || [])
                .map((category) => getSectionTitleByCategory(category))
                .filter(Boolean);

            acceptedCategoriesElement.textContent = acceptedCategoryTitles.join(', ') || '';
        }
    }    
};
document.addEventListener('DOMContentLoaded', initCookieConsent);