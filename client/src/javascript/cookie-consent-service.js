import * as CookieConsent from 'vanilla-cookieconsent';
import '@orestbida/iframemanager/src/iframemanager';

const externalMediaServices = {
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
    _hooks: {},
    on(hookName, callback) {
        if (typeof callback !== 'function') {
            return;
        }
        if (!this._hooks[hookName]) {
            this._hooks[hookName] = [];
        }
        this._hooks[hookName].push(callback);
    },
    emit(hookName, ...args) {
        (this._hooks[hookName] || []).forEach(callback => callback(...args));
    },
    getCookieConsentApi() {
        return CookieConsent;
    },
    getServerSideConfiguration() {
        return window.cookieConsentConfig || {};
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
            isIframeManagerDisabled: serverSideConfig?.isIframeManagerDisabled || false,
            externalMediaCategory: serverSideConfig?.externalMediaCategory || 'embeds',
        };
    },

    isGoogleConsentModeEnabled() {
        return this.getConsentSettings().isGoogleConsentModeEnabled;
    },
    isConsentRegistrationEnabled() {
        return this.getConsentSettings().isConsentRegistrationEnabled;
    },
    isIframeManagerDisabled() {
        return this.getConsentSettings().isIframeManagerDisabled;
    },
    getDefaultLanguage() {
        return this.getConsentSettings().defaultLanguage;
    },
    getCookieConsentTranslations() {
        return this.getConsentSettings().translations;
    },
    getGuiOptions() {
        return this.getConsentSettings().guiOptions;
    },

    getConsentCategories() {
        const { categories, externalMediaCategory, isIframeManagerDisabled } = this.getConsentSettings();

        if (isIframeManagerDisabled) {
            return categories;
        }

        const externalCategory = categories?.[externalMediaCategory];
        if (!externalCategory?.services) {
            return categories;
        }

        return {
            ...categories,
            [externalMediaCategory]: {
                ...externalCategory,
                services: Object.fromEntries(
                    Object.entries(externalCategory.services).map(([key, service]) => [
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
    },
    updateGtagConsent() {
        if (!this.isGoogleConsentModeEnabled()) {
            return;
        }
        window.dataLayer = window.dataLayer || [];
        window.gtag = window.gtag || function () {
            window.dataLayer.push(arguments);
        };
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
        if (!this.isConsentRegistrationEnabled()) {
            return;
        }

        const cookie = CookieConsent.getCookie();
        const preferences = CookieConsent.getUserPreferences();

        if (!cookie || !preferences) {
            return;
        }

        const consentData = [];

        if (preferences.acceptType) {
            consentData.push('Consent Type: ' + preferences.acceptType);
        }

        const acceptedCategoryTitles = this.getAcceptedCategoryTitles(cookie);

        if (acceptedCategoryTitles.length > 0) {
            consentData.push('Accepted Categories: ' + acceptedCategoryTitles.join(', '));
        }

        const rejectedCategoryTitles = this.getRejectedCategoryTitles(cookie);

        if (rejectedCategoryTitles.length > 0) {
            consentData.push('Rejected Categories: ' + rejectedCategoryTitles.join(', '));
        }

        const acceptedServiceLabels = this.getAcceptedServiceLabels(preferences);

        if (acceptedServiceLabels.length > 0) {
            consentData.push('Accepted Services: ' + acceptedServiceLabels.join(', '));
        }

        const rejectedServiceLabels = this.getRejectedServiceLabels(preferences);

        if (rejectedServiceLabels.length > 0) {
            consentData.push('Rejected Services: ' + rejectedServiceLabels.join(', '));
        }

        const userConsent = {
            ConsentID: cookie.consentId || '',
            ConsentType: 'CookieConsent',
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
    getAcceptedServiceLabels(preferences) {
        return this._getServiceLabels(preferences?.acceptedServices);
    },
    getRejectedServiceLabels(preferences) {
        return this._getServiceLabels(preferences?.rejectedServices);
    },
    _getServiceLabels(servicesByCategory) {
        const { categories } = this.getConsentSettings();

        return Object.entries(servicesByCategory || {})
            .filter(([, services]) => Array.isArray(services))
            .reduce((all, [category, services]) => all.concat(
                services.map((service) => categories?.[category]?.services?.[service]?.label || service)
            ), []);
    },
    buildIframeManagerConfig() {
        const { externalMediaCategory } = this.getConsentSettings();

        return {
            currLang: this.getDefaultLanguage(),
            services: this.getExternalMediaServices(),
            onChange: ({ changedServices, eventSource }) => {
                if (eventSource.type === 'click') {
                    const acceptedServices = CookieConsent.getUserPreferences().acceptedServices?.[externalMediaCategory] ?? [];
                    const servicesToAccept = [...new Set([...acceptedServices, ...changedServices])];
                    CookieConsent.acceptService(servicesToAccept, externalMediaCategory);
                }
            }
        };
    },
    getAcceptedCategoryTitles(cookie) {
        return this.getCategoryTitles(cookie?.categories || []);
    },
    getRejectedCategoryTitles(cookie) {
        const acceptedCategories = cookie?.categories || [];
        const allCategories = Object.keys(this.getConsentCategories() || {});

        return this.getCategoryTitles(
            allCategories.filter((category) => !acceptedCategories.includes(category))
        );
    },
    getCategoryTitles(categories) {
        const { translations, defaultLanguage } = this.getConsentSettings();
        const sections = translations?.[defaultLanguage]?.preferencesModal?.sections || [];

        return categories
            .map((category) => sections.find((item) => item.linkedCategory === category)?.title)
            .filter(Boolean);
    },
    getExternalMediaServices() {
        const { externalMediaServiceTranslations } = this.getConsentSettings();

        if (!externalMediaServiceTranslations || Object.keys(externalMediaServiceTranslations).length === 0) {
            return {};
        }
        return Object.fromEntries(
            Object.entries(externalMediaServiceTranslations).map(([key, config]) => [
                key,
                {
                    ...externalMediaServices[key],
                    ...config,
                    languages: {
                        ...(externalMediaServices[key]?.languages || {}),
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
