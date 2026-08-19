import * as CookieConsent from 'vanilla-cookieconsent';
import iframemanager from '@orestbida/iframemanager/src/iframemanager';

const externalMediaServices = {
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
        languages: {}
    },
    youtube: {
        embedUrl: 'https://www.youtube-nocookie.com/embed/{data-id}',
        thumbnailUrl: 'https://i3.ytimg.com/vi/{data-id}/hqdefault.jpg',
        iframe: {
            allow: 'accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen;',
        },
        languages: {}
    }
};

export const cookieConsentService = {
    getCookieConsentApi() {
        return CookieConsent;
    },
    getIframeManagerApi() {
        return iframemanager();
    },
    getServerSideConfiguration() {
        return window.cookieConsentConfig || {};
    },
    getCategories() {
        const serverSideConfig = this.getServerSideConfiguration();
        return serverSideConfig?.categories || {
            functional: {
                readOnly: true
            }
        };
    },
    initializeGtagConsent() {
        window.dataLayer = window.dataLayer || [];
        window.gtag = window.gtag || function () {
            window.dataLayer.push(arguments);
        };
    },
    updateGtagConsent() {
        window.gtag('consent', 'update', {
            functionality_storage: CookieConsent.acceptedCategory('functionality') ? 'granted' : 'denied',
            personalization_storage: CookieConsent.acceptedCategory('personalization') ? 'granted' : 'denied',
            analytics_storage: CookieConsent.acceptedCategory('analytics') ? 'granted' : 'denied',
            ad_storage: CookieConsent.acceptedCategory('marketing') ? 'granted' : 'denied',
            ad_user_data: CookieConsent.acceptedCategory('marketing') ? 'granted' : 'denied',
            ad_personalization: CookieConsent.acceptedCategory('marketing') ? 'granted' : 'denied',
            security_storage: 'granted'
        });
    },
    registerConsent() {
        const cookie = CookieConsent.getCookie();
        const preferences = CookieConsent.getUserPreferences();

        if (!cookie || !preferences) {
            return;
        }

        const consentData = [];

        if (preferences.acceptedCategories && preferences.acceptedCategories.length) {
            consentData.push('Accepted Categories: ' + preferences.acceptedCategories.join(', '));
        }

        if (preferences.rejectedCategories && preferences.rejectedCategories.length) {
            consentData.push('Rejected Categories: ' + preferences.rejectedCategories.join(', '));
        }

        const userConsent = {
            ConsentID: cookie.consentId || '',
            ConsentType: 'Cookies',
            ConsentStatement: 'N/A',
            ConsentData: consentData.join(', '),
            URL: window.location.href
        };

        fetch('/consent/register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(userConsent)
        }).catch((error) => {
            console.error('Failed to register consent', error);
        });
    },
    getExternalMediaServices() {
        const translations = window.cookieConsentConfig?.externalMediaServices?.services || {};

        return Object.fromEntries(
            Object.entries({ ...externalMediaServices, ...translations }).map(([key, config]) => [
                key,
                {
                    ...externalMediaServices[key],
                    ...config,
                    languages: {
                        ...(externalMediaServices[key]?.languages || {}),
                        ...(config.languages || {})
                    },
                    onAccept: () => {
                        window.iframemanager().acceptService(key);
                    },
                    onReject: () => {
                        window.iframemanager().rejectService(key);
                    }
                }
            ])
        );
    },
    addExternalMediaService(key, config) {
        if (!key || !config) {
            return null;
        }

        externalMediaServices[key] = {
            ...config,
            iframe: {
                ...(config.iframe || {})
            },
            languages: {
                ...(config.languages || {})
            }
        };

        return externalMediaServices[key];
    }
};
window.cookieConsentService = cookieConsentService;
