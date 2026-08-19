<div class="cookie-consent">
    <% if $ConsentID %>
        <p class="cookie-consent__meta">
            <span class="cookie-consent__label cookie-consent__label--id"><%t CookieConsent.ConsentID 'Consent ID' %></span>: <span class="cookie-consent__value cookie-consent__value--id" id="cookie-consent-id">$ConsentID</span><br/>
            <span class="cookie-consent__label cookie-consent__label--date"><%t CookieConsent.Date 'Consent given' %></span>: <span class="cookie-consent__value cookie-consent__value--date" id="cookie-consent-timestamp">$ConsentDate</span><br/>
            <span class="cookie-consent__label cookie-consent__label--categories"><%t CookieConsent.AcceptedCategories 'Accepted Categories' %></span>: <span class="cookie-consent__value cookie-consent__value--categories" id="cookie-consent-accepted-categories">$AcceptedCategories</span>
        </p>
        <p class="cookie-consent__actions">
            <a class="cookie-consent__button" type="button" data-cc="show-consentModal"><%t CookieConsent.ShowConsentModal 'Change Your Cookie Preferences' %></a>
        </p>
    <% end_if %>

    <% loop $Categories %>
        <section class="cookie-consent__category">
            <h3 class="cookie-consent__category-title">$Title</h3>
            <div class="cookie-consent__category-content">
                $Content
            </div>
            <table class="cookie-consent__table">
                <thead class="cookie-consent__table-head">
                    <tr>
                        <th><%t CookieConsent.CookieName 'Name' %></th>
                        <th><%t CookieConsent.CookieProvider  'Provider' %></th>
                        <th><%t CookieConsent.CookieDescription 'Description' %></th>
                        <th><%t CookieConsent.CookieExpiration 'Expiration' %></th>
                    </tr>
                </thead>
                <tbody class="cookie-consent__table-body">
                    <% loop $CookieDescriptions %>
                        <tr class="cookie-consent__table-row">
                            <td class="cookie-consent__table-cell cookie-consent__table-cell--name">$Name</td>
                            <td class="cookie-consent__table-cell cookie-consent__table-cell--provider">$Service</td>
                            <td class="cookie-consent__table-cell cookie-consent__table-cell--description">$Description</td>
                            <td class="cookie-consent__table-cell cookie-consent__table-cell--expiration">$Expiration</td>
                        </tr>
                    <% end_loop %>
                </tbody>
            </table>
        </section>
    <% end_loop %>
</div>
