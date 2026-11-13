import * as CookieConsent from 'vanilla-cookieconsent';
import '@orestbida/iframemanager/src/iframemanager';

const externalMediaServices = {
    youtube: {
        embedUrl: 'https://www.youtube-nocookie.com/embed/{data-id}',
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
            externalMediaServiceSelections: serverSideConfig?.externalMediaServices?.services || {},
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
        const settings = this.getConsentSettings();
        const { externalMediaCategory, isIframeManagerDisabled } = settings;
        const categories = this._withAutoClearPatterns(settings.categories);

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
    // JSON cannot carry a RegExp, so wildcard cookies arrive as '/^(name)/' strings.
    _withAutoClearPatterns(categories) {
        return Object.fromEntries(Object.entries(categories).map(([key, category]) => [
            key,
            !category.autoClear ? category : {
                ...category,
                autoClear: {
                    ...category.autoClear,
                    cookies: category.autoClear.cookies.map(({ name, ...rest }) => {
                        const pattern = name.match(/^\/(\^\(.+\))\/$/);
                        return { ...rest, name: pattern ? new RegExp(pattern[1]) : name };
                    })
                }
            }
        ]));
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
            functionality_storage: CookieConsent.acceptedCategory('functional') ? 'granted' : 'denied',
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

        // Language-independent keys, so records can be queried; titles are translated and unreliable.
        const acceptedCategories = preferences.acceptedCategories || [];
        const consentData = {
            accepted: acceptedCategories,
            rejected: Object.keys(this.getConsentCategories() || {}).filter((key) => !acceptedCategories.includes(key)),
            acceptedServices: preferences.acceptedServices || {},
            rejectedServices: preferences.rejectedServices || {}
        };

        // The text the visitor was shown, as evidence of what they chose between.
        const { translations, defaultLanguage } = this.getConsentSettings();
        const modal = translations?.[defaultLanguage]?.consentModal || {};
        const statement = [modal.title, modal.description].filter(Boolean).join('\n\n');

        const userConsent = {
            ConsentID: cookie.consentId || '',
            ConsentType: 'CookieConsent',
            ConsentStatement: statement,
            // A JSON string: the endpoint accepts scalars only.
            ConsentData: JSON.stringify(consentData),
            // No query string or hash: they can carry personal data and overflow the URL column.
            URL: window.location.origin + window.location.pathname
        };

        const url = this.getServerSideConfiguration().consentRegistrationUrl;
        const send = () => fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(userConsent)
        }).then((response) => {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
        });

        // fetch only rejects on network errors, so HTTP errors are thrown above; retry once.
        send().catch(() => new Promise((resolve) => setTimeout(resolve, 2000)).then(send)).catch((error) => {
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
    getAcceptedCategoryTitles(preferences) {
        return this.getCategoryTitles(preferences?.acceptedCategories || []);
    },
    getRejectedCategoryTitles(preferences) {
        const acceptedCategories = preferences?.acceptedCategories || [];
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
        const { externalMediaServiceSelections } = this.getConsentSettings();

        if (!externalMediaServiceSelections || Object.keys(externalMediaServiceSelections).length === 0) {
            return {};
        }
        return Object.fromEntries(
            Object.entries(externalMediaServiceSelections).map(([key, config]) => [
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
