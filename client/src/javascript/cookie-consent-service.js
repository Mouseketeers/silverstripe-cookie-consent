import * as CookieConsent from 'vanilla-cookieconsent';

export const cookieConsentService = {
    async loadConfiguration() {
        try {
            const response = await fetch('/cookie-consent/configuration', {
                method: 'GET',
                headers: {
                    Accept: 'application/json'
                }
            });

            if (response.ok) {
                const endpointConfig = await response.json();
                if (endpointConfig && typeof endpointConfig === 'object') {
                    return endpointConfig;
                }
            }
        } catch (error) {
            console.error('Failed to load cookie consent configuration', error);
        }

        return {};
    },
	getCookieConsentApi() {
		return CookieConsent;
	},
	async getServerSideConfiguration() {
		const config = await this.loadConfiguration();
		return config || {};
	},
    initializeGtagConsent(requireExplicitConsent = true) {
        window.dataLayer = window.dataLayer || [];
        window.gtag = window.gtag || function () {
            window.dataLayer.push(arguments);
        };

        window.gtag('consent', 'default', {
            ad_storage: requireExplicitConsent ? 'denied' : 'granted',
            ad_user_data: requireExplicitConsent ? 'denied' : 'granted',
            ad_personalization: requireExplicitConsent ? 'denied' : 'granted',
            analytics_storage: requireExplicitConsent ? 'denied' : 'granted',
            functionality_storage: requireExplicitConsent ? 'denied' : 'granted',
            personalization_storage: requireExplicitConsent ? 'denied' : 'granted',
            security_storage: 'granted',
        });
    },
    updateGtagConsent() {

        if (typeof window.gtag !== 'function') {
            return;
        }

        window.gtag('consent', 'update', {
            analytics_storage: CookieConsent.acceptedCategory('analytics') ? 'granted' : 'denied',
            ad_storage: CookieConsent.acceptedCategory('advertisement') ? 'granted' : 'denied',
            ad_user_data: CookieConsent.acceptedCategory('advertisement') ? 'granted' : 'denied',
            ad_personalization: CookieConsent.acceptedCategory('advertisement') ? 'granted' : 'denied',
            functionality_storage: CookieConsent.acceptedCategory('functionality') ? 'granted' : 'denied',
            personalization_storage: CookieConsent.acceptedCategory('functionality') ? 'granted' : 'denied',
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
    }
};
window.cookieConsentService = cookieConsentService;
