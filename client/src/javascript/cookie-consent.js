import { cookieConsentService } from './cookie-consent-service';

function initCookieConsent() {

    const cookieConsentApi = cookieConsentService.getCookieConsentApi();
    const iframeManagerApi = iframemanager();
    const iframeServices = ['youtube'];
    const serverSideConfig = cookieConsentService.getServerSideConfiguration();
    const defaultLanguage = serverSideConfig?.defaultLanguage || 'en';
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

    if (isGoogleConsentModeEnabled) {
        cookieConsentService.initializeGtagConsent();
    }

    const translations = serverSideConfig?.translations || {};

    const categories = serverSideConfig?.categories || {
        functional: {
            readOnly: true
        }
    };


    console.log(categories);

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
            translations
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

    // const consentEmbedServices = categories?.embeds?.services || {};

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

            languages: {
                en: {
                    notice: 'This content is hosted by a third party. By showing the external content you accept the <a rel="noreferrer noopener" href="https://vimeo.com/terms" target="_blank">terms and conditions</a> of vimeo.com.',
                    loadBtn: 'Load video',
                    loadAllBtn: "Don't ask again"
                }
            }
        },
        youtube: {
            embedUrl: 'https://www.youtube-nocookie.com/embed/{data-id}',

            thumbnailUrl: 'https://i3.ytimg.com/vi/{data-id}/hqdefault.jpg',

            iframe: {
                allow: 'accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen;',
            },

            languages: {
                en: {
                    notice: 'This content is hosted by a third party. By showing the external content you accept the <a rel="noreferrer noopener" href="https://www.youtube.com/t/terms" target="_blank">terms and conditions</a> of youtube.com.',
                    loadBtn: 'Load video',
                    loadAllBtn: "Don't ask again"
                }
            }
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
};
document.addEventListener('DOMContentLoaded', initCookieConsent);