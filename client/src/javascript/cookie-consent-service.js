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
    },
    googlemaps: {
        embedUrl: 'https://www.google.com/maps/embed?pb={data-id}',
        iframe: {
            allow: 'fullscreen; picture-in-picture;',
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
        return this.getConsentSettings().isGoogleConsentModeEnabled;
    },
    isConsentRegistrationEnabled() {
        return this.getConsentSettings().isConsentRegistrationEnabled;
    },
    getDefaultLanguage() {
        return this.getConsentSettings().defaultLanguage;
    },
    getCookieConsentTranslations() {
        return this.getConsentSettings().translations;
    },
    getExternalMediaTranslations() {
        return this.getConsentSettings().externalMediaServices;
    },
    getServerSideConfiguration() {
        return window.cookieConsentConfig || {};
    },
    getGuiOptions() {
        return this.getConsentSettings().guiOptions;
    },
    getConsentSettings() {
        const serverSideConfig = this.getServerSideConfiguration();

        return {
            categories: serverSideConfig?.categories || {
                functional: {
                    readOnly: true
                }
            },
            defaultLanguage: serverSideConfig?.defaultLanguage || 'en',
            guiOptions: serverSideConfig?.guiOptions || {},
            translations: serverSideConfig?.translations || {},
            externalMediaServiceTranslations: serverSideConfig?.externalMediaServices?.services || {},
            isGoogleConsentModeEnabled: serverSideConfig?.isGoogleConsentModeEnabled || false,
            isConsentRegistrationEnabled: serverSideConfig?.isConsentRegistrationEnabled || false,
            isExternalMediaManagementEnabled: serverSideConfig?.isExternalMediaManagementEnabled || false,
            externalMediaCategory: serverSideConfig?.externalMediaCategory || 'embeds',
        };
    },
    getConsentCategories() {
        const { categories, externalMediaCategory } = this.getConsentSettings();

        if (!externalMediaCategory) {
            return categories;
        }

        const categoryServices = categories?.[externalMediaCategory]?.services || {};

        const mergedCategories = {
            ...categories,
            [externalMediaCategory]: {
                ...(categories?.[externalMediaCategory] || {}),
                services: Object.fromEntries(
                    Object.entries(categoryServices).map(([key, service]) => [
                        key,
                        {
                            ...service,
                            onAccept: () => window.iframemanager().acceptService(key),
                            onReject: () => window.iframemanager().rejectService(key)
                        }
                    ])
                )
            }
        };
        return mergedCategories;
    },
    initializeGtagConsent() {
        window.dataLayer = window.dataLayer || [];
        window.gtag = window.gtag || function () {
            window.dataLayer.push(arguments);
        };
    },
    updateGtagConsent() {

        if(!this.isGoogleConsentModeEnabled()) {
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

        if(preferences.acceptType) {
            consentData.push('Consent Type: ' + preferences.acceptType);
        }

        if (preferences.acceptedCategories && preferences.acceptedCategories.length) {
            consentData.push('Accepted Categories: ' + preferences.acceptedCategories.join(', '));
        }

        if (preferences.rejectedCategories && preferences.rejectedCategories.length) {
            consentData.push('Rejected Categories: ' + preferences.rejectedCategories.join(', '));
        }

        const acceptedServicesByCategory = Object.entries(preferences.acceptedServices || {})
            .filter(([, services]) => Array.isArray(services) && services.length > 0)
            .map(([,services]) => `${services.join(', ')}`)
            .join('; ');
        
        if (acceptedServicesByCategory) {
            consentData.push('Accepted Services: ' + acceptedServicesByCategory);
        }

        const rejectedServicesByCategory = Object.entries(preferences.rejectedServices || {})
            .filter(([, services]) => Array.isArray(services) && services.length > 0)
            .map(([, services]) => `${services.join(', ')}`)
            .join('; ');

        if (rejectedServicesByCategory) {
            consentData.push('Rejected Services: ' + rejectedServicesByCategory);
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
        const { externalMediaCategory } = this.getConsentSettings();

        return {
            currLang: this.getDefaultLanguage(),
            services: this.getExternalMediaServices(),
            onChange: ({ changedServices, eventSource }) => {
                if (eventSource.type === 'click') {
                    const servicesToAccept = [
                        ...CookieConsent.getUserPreferences().acceptedServices[externalMediaCategory],
                        ...changedServices
                    ];
                    CookieConsent.acceptService(servicesToAccept, externalMediaCategory);
                }
            }
        };
    },
    getAcceptedCategoryTitles(cookie) {
        const { translations, defaultLanguage } = this.getConsentSettings();
        const sections = translations?.[defaultLanguage]?.preferencesModal?.sections || [];

        const getSectionTitleByCategory = (category) =>
            sections.find((item) => item.linkedCategory === category)?.title;

        return (cookie?.categories || [])
            .map((category) => getSectionTitleByCategory(category))
            .filter(Boolean);
    },
    getExternalMediaServices() {
        const { externalMediaServiceTranslations } = this.getConsentSettings();

        if(!externalMediaServiceTranslations || Object.keys(externalMediaServiceTranslations).length === 0) {
            return {};
        }
        const mergedExternalMediaServices = Object.fromEntries(
            Object.entries(externalMediaServiceTranslations).map(([key, config]) => [
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
        return mergedExternalMediaServices;
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
