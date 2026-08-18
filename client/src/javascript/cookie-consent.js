import { cookieConsentService } from './cookie-consent-service';

function initCookieConsent() {

    const cookieConsentApi = cookieConsentService.getCookieConsentApi();
    const iframeManagerApi = iframemanager();
    const serverSideConfig = cookieConsentService.getServerSideConfiguration();
    const defaultLanguage = serverSideConfig?.defaultLanguage || 'en';
    const isGoogleConsentModeEnabled = serverSideConfig?.isGoogleConsentModeEnabled || false;
    const isConsentRegistrationEnabled = serverSideConfig?.isConsentRegistrationEnabled || false;
    const iframeMangerServices = serverSideConfig?.iframeManager?.services || {};

    if (isGoogleConsentModeEnabled) {
        cookieConsentService.initializeGtagConsent();
    }

    const categories = serverSideConfig?.categories || {
        functional: {
            readOnly: true
        }
    };

    if(iframeMangerServices) {
        console.log('iframeMangerServices', iframeMangerServices);
    }

    for (const category in categories) {
        let services = categories[category].services;

        if (services === undefined) continue;

         for (const serviceKey in services) {
             services[serviceKey].onAccept = () => {
                 window.iframemanager().acceptService(serviceKey);
             };
             services[serviceKey].onReject = () => {
                 window.iframemanager().rejectService(serviceKey);
             };
         }
    }

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
    cookieConsentApi.run(cookieConsentConfig);

    // const iframeMangerServices = serverSideConfig?.iframeManager?.services || {};

    const services = {
        vimeo: {
            embedUrl: 'https://player.vimeo.com/video/{data-id}',
            iframe: {
                allow: 'fullscreen; picture-in-picture, allowfullscreen;',
            },

            thumbnailUrl: async (dataId, setThumbnail) => {
                const url = `https://vimeo.com/api/v2/video/${dataId}.json`;
                const response = await (await fetch(url)).json();
                const thumbnailUrl = response[0]?.thumbnail_large;
                thumbnailUrl && setThumbnail(thumbnailUrl);
            },
            languages: iframeMangerServices?.vimeo?.languages || {}
        },
        youtube: {
            embedUrl: 'https://www.youtube-nocookie.com/embed/{data-id}',
            thumbnailUrl: 'https://i3.ytimg.com/vi/{data-id}/hqdefault.jpg',
            iframe: {
                allow: 'accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen;',
            },
            languages: iframeMangerServices?.youtube?.languages || {}
        }
    };

    const iframeManagerConfig = {
        currLang: serverSideConfig?.defaultLanguage || 'en',
        services,
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