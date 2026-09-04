<div class="cookie-consent">
    <div id="cookie-consent__header" style="display: none;">
        <p>
            <span id="cookie-consent-row-id" style="display: none;">
                <%t CookieConsent.ConsentID 'Consent ID' %>: <span id="cookie-consent-id"></span><br/>
            </span>
            <span id="cookie-consent-row-timestamp" style="display: none;">
                <%t CookieConsent.ConsentDate 'Consent given on' %>: <span id="cookie-consent-timestamp"></span><br/>
            </span>
            <span id="cookie-consent-row-accepted-categories" style="display: none;">
                <%t CookieConsent.AcceptedCategories 'Accepted Categories' %>: <span id="cookie-consent-accepted-categories"></span><br/>
            </span>
            <span id="cookie-consent-row-rejected-categories" style="display: none;">
                <%t CookieConsent.RejectedCategories 'Rejected Categories' %>: <span id="cookie-consent-rejected-categories"></span><br/>
            </span>
            <span id="cookie-consent-row-accepted-services" style="display: none;">
                <%t CookieConsent.AcceptedServices 'Accepted Services' %>: <span id="cookie-consent-accepted-services"></span><br/>
            </span>
            <span id="cookie-consent-row-rejected-services" style="display: none;">
                <%t CookieConsent.RejectedServices 'Rejected Services' %>: <span id="cookie-consent-rejected-services"></span>
            </span>
        </p>
        <p>
            <a class="cookie-consent__button" type="button" data-cc="show-preferencesModal"><%t CookieConsent.ShowPreferencesModal 'Change Your Cookie Preferences' %></a>
        </p>
    </div>

    <% loop $Categories %>
        <section class="cookie-consent__category">
            <h3>$Title</h3>
            <table class="cookie-consent__table">
                <thead>
                    <tr>
                        <th><%t CookieConsent.CookieName 'Name' %></th>
                        <th><%t CookieConsent.CookieProvider  'Provider' %></th>
                        <th><%t CookieConsent.CookieDescription 'Description' %></th>
                        <th><%t CookieConsent.CookieExpiration 'Expiration' %></th>
                    </tr>
                </thead>
                <tbody>
                    <% loop $CookieDescriptions %>
                        <tr>
                            <td>$Name</td>
                            <td>
                                <% if $PrivacyPolicyURL && $Provider %>
                                    <a href="$PrivacyPolicyURL" target="_blank" rel="noopener noreferrer">$Provider</a>
                                <% else %>
                                    $Provider
                                <% end_if %>
                            </td>
                            <td>$Description</td>
                            <td>$Expiration</td>
                        </tr>
                    <% end_loop %>
                </tbody>
            </table>
        </section>
    <% end_loop %>
</div>
