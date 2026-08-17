import { cookieConsentService } from './cookie-consent-service';

function initCookieConsent() {

    const cookieConsentApi = cookieConsentService.getCookieConsentApi();
    const iframeManager = iframemanager();
    const iframeServices = ['youtube'];
    const serverSideConfig = cookieConsentService.getServerSideConfiguration();
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

    function syncIframeServicesWithConsent() {
        const isMarketingAccepted = cookieConsentApi.acceptedCategory('marketing');
        const acceptedServices = cookieConsentApi.getUserPreferences()?.acceptedServices?.['marketing'] || [];

        iframeServices.forEach((serviceName) => {
            if (!isMarketingAccepted || !acceptedServices.includes(serviceName)) {
                iframeManager.rejectService(serviceName);
                return;
            }

            iframeManager.acceptService(serviceName);
        });
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
            const defaultLanguage = serverSideConfig?.defaultLanguage || 'en';

            const sections = serverSideConfig?.translations?.[defaultLanguage]?.preferencesModal?.sections || [];
            const getSectionTitleByCategory = (category) =>
                sections.find((item) => item.linkedCategory === category)?.name || category;
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

    categories['marketing'] = categories['marketing'] || {};
    categories['marketing'].services = {
        youtube: {
            label: 'Youtube',
            onAccept: () => iframeManager.acceptService('youtube'),
            onReject: () => iframeManager.rejectService('youtube')
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
            updateCookieConsentDeclaration();
            // syncIframeServicesWithConsent();
        },
        onConsent: () => {
            updateGtagConsent();
            // syncIframeServicesWithConsent();
        },
        onChange: () => {
            updateGtagConsent();
            registerConsent();
            updateCookieConsentDeclaration();
            // syncIframeServicesWithConsent();

        }
    };
    cookieConsentApi.run(defaultConfig);


    iframeManager.run({
        currLang: 'en',
        services: {
            youtube: {
                embedUrl: 'https://www.youtube-nocookie.com/embed/{data-id}?{data-params}',
                thumbnailUrl: 'https://i3.ytimg.com/vi/{data-id}/hqdefault.jpg',
                iframe: {
                    allow: 'accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen;'
                },
                languages: {
                    en: {
                        loadAllBtn: "Accept Marketing Cookies to View Video",
                    }
                }
            }
        },
        onChange: ({ changedServices, eventSource }) => {
            if (eventSource.type === 'click') {
                const isMarketingAccepted = cookieConsentApi.acceptedCategory('marketing');

                // if (!isMarketingAccepted) {
                //     changedServices.forEach((serviceName) => iframeManager.rejectService(serviceName));
                //     cookieConsentApi.showPreferences();
                //     return;
                // }

                const acceptedMarketingServices = cookieConsentApi.getUserPreferences()?.acceptedServices?.['marketing'] || [];
                const servicesToAccept = [
                    ...acceptedMarketingServices,
                    ...changedServices,
                ];

                cookieConsentApi.acceptService([...new Set(servicesToAccept)], 'marketing');
                syncIframeServicesWithConsent();

                // const acceptedMarketingServices = cookieConsentApi.getUserPreferences()?.acceptedServices?.marketing || [];
                // console.log('Changed services:', changedServices);
                // console.log('Accepted marketing services:', acceptedMarketingServices);
                // const servicesToAccept = [
                //     ...acceptedMarketingServices,
                //     ...changedServices,
                // ];

                // cookieConsentApi.acceptService(servicesToAccept, 'marketing');                
            }
        }
    });
};
document.addEventListener('DOMContentLoaded', initCookieConsent);