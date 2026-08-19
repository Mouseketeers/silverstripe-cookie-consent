import * as CookieConsent from 'vanilla-cookieconsent';
import iframemanager from '@orestbida/iframemanager/src/iframemanager';

const defaultExternalMediaServices = {
    youtube: {
        embedUrl: 'https://www.youtube-nocookie.com/embed/{data-id}',
        thumbnailUrl: 'https://i3.ytimg.com/vi/{data-id}/hqdefault.jpg',
        iframe: {
            allow: 'accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen;',
        },
        languages: {}
    },
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
    }
};

export const cookieConsentService = {
    init() {
        if(this.isGoogleConsentModeEnabled()) {
            cookieConsentService.initializeGtagConsent();
        }
    },
    getCookieConsentApi() {
        return CookieConsent;
    },
    isGoogleConsentModeEnabled() {
        const serverSideConfig = this.getServerSideConfiguration();
        return serverSideConfig?.isGoogleConsentModeEnabled || false;
    },
    isConsentRegistrationEnabled() {
        const serverSideConfig = this.getServerSideConfiguration();
        return serverSideConfig?.isConsentRegistrationEnabled || false;
    },
    getDefaultLanguage() {
        const serverSideConfig = this.getServerSideConfiguration();
        return serverSideConfig?.defaultLanguage || 'en';
    },
    getCookieConsentTranslations() {
        const serverSideConfig = this.getServerSideConfiguration();
        return serverSideConfig?.translations || {};
    },
    getExternalMediaTranslations() {
        const serverSideConfig = this.getServerSideConfiguration();
        return serverSideConfig?.externalMediaServices?.services || {};
    },
    getServerSideConfiguration() {
        return window.cookieConsentConfig || {};
    },
    getConsentCategories() {
        const serverSideConfig = this.getServerSideConfiguration();
        const categories = serverSideConfig?.categories || {
            functional: {
                readOnly: true
            }
        };

        if (!serverSideConfig?.externalMediaServices?.services) {
            return categories;
        }

        return Object.fromEntries(
            Object.entries(categories).map(([categoryKey, categoryConfig]) => {
                const services = categoryConfig?.services;

                if (!services) {
                    return [categoryKey, categoryConfig];
                }

                const mappedServices = Object.fromEntries(
                    Object.entries(services).map(([serviceKey, serviceConfig]) => [
                        serviceKey,
                        {
                            ...serviceConfig,
                            onAccept: () => window.iframemanager().acceptService(serviceKey),
                            onReject: () => window.iframemanager().rejectService(serviceKey)
                        }
                    ])
                );

                return [categoryKey, { ...categoryConfig, services: mappedServices }];
            })
        );
    },
    initializeGtagConsent() {
        window.dataLayer = window.dataLayer || [];
        window.gtag = window.gtag || function () {
            window.dataLayer.push(arguments);
        };
    },
    updateGtagConsent() {

        if(!this.isGoogleConsentModeEnabled) {
            return;
        }
        
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
        
        if(!this.isConsentRegistrationEnabled()) {
            return;
        }
        
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
    buildIframeManagerConfig() {
       
        return {
            currLang: this.getDefaultLanguage(),
            services: this.getExternalMediaServices(),
            onChange: ({ changedServices, eventSource }) => {
                if (eventSource.type === 'click') {
                    const servicesToAccept = [
                        ...CookieConsent.getUserPreferences().acceptedServices['embeds'],
                        ...changedServices
                    ];
                    CookieConsent.acceptService(servicesToAccept, 'embeds');
                }
            }
        };
    },
    getAcceptedCategoryTitles(cookie) {
        const translations = this.getCookieConsentTranslations();
        const defaultLanguage = this.getDefaultLanguage();
        const sections = translations?.[defaultLanguage]?.preferencesModal?.sections || [];

        const getSectionTitleByCategory = (category) =>
            sections.find((item) => item.linkedCategory === category)?.title;

        return (cookie?.categories || [])
            .map((category) => getSectionTitleByCategory(category))
            .filter(Boolean);
    },
    getExternalMediaServices() {
        const externalMediaTranslations = this.getExternalMediaTranslations();

        return Object.fromEntries(
            Object.entries({ ...defaultExternalMediaServices, ...externalMediaTranslations }).map(([key, config]) => [
                key,
                {
                    ...defaultExternalMediaServices[key],
                    ...config,
                    languages: {
                        ...(defaultExternalMediaServices[key]?.languages || {}),
                        ...(config.languages || {})
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
