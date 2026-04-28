/**
 * EN: Implements control-panel module behavior and admin-country operations in `control-panel/js/hr/countries-cities-handler.js`.
 * AR: ينفذ سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/js/hr/countries-cities-handler.js`.
 */
// Wait for the countries cities data to load
document.addEventListener('DOMContentLoaded', function() {
    var hrCountriesListFetch = null;
    var hrPopulateCountryDebounce = null;

    function schedulePopulateCountryDropdown() {
        clearTimeout(hrPopulateCountryDebounce);
        hrPopulateCountryDebounce = setTimeout(function() {
            hrPopulateCountryDebounce = null;
            populateCountryDropdown();
        }, 120);
    }

    function ratibApiRoot() {
        var t = (typeof getRatibApiBaseTrimmed === 'function') ? getRatibApiBaseTrimmed() : '';
        if (t) return t;
        // getRatibApiBaseTrimmed() is '' in control HR shell — still use main /api from app-config for countries/cities
        var b = (window.APP_CONFIG && window.APP_CONFIG.apiBase) || '';
        b = String(b || '').replace(/\/+$/, '');
        if (b) return b;
        var el = document.getElementById('app-config');
        if (el) {
            b = (el.getAttribute('data-api-base') || '').replace(/\/+$/, '');
            if (b) return b;
        }
        var htmlBase = (document.documentElement.getAttribute('data-base-url') || '').replace(/\/+$/, '');
        return htmlBase ? htmlBase + '/api' : '';
    }

    function bundledCountriesMap() {
        return (typeof window !== 'undefined' && (window.countriesCities || window.STATIC_COUNTRIES_CITIES)) || null;
    }

    /** When recruitment_countries has a country but no city rows, API returns []. Use static list from countries-cities.js. */
    function fallbackCitiesForCountry(country) {
        if (!country) return [];
        var map = bundledCountriesMap();
        if (!map || typeof map !== 'object') return [];
        if (Array.isArray(map[country]) && map[country].length) {
            return map[country].slice();
        }
        var lower = String(country).toLowerCase().trim();
        for (var key in map) {
            if (Object.prototype.hasOwnProperty.call(map, key) && String(key).toLowerCase().trim() === lower) {
                return Array.isArray(map[key]) ? map[key].slice() : [];
            }
        }
        return [];
    }

    function useBundledCountriesCities() {
        var el = document.getElementById('app-config');
        if (window.APP_CONFIG && window.APP_CONFIG.controlHrApiBase) return true;
        if (el && el.getAttribute('data-control-hr-api-base')) return true;
        return false;
    }

    // Function to load cities by country from API
    async function loadCitiesByCountry(country, cityFieldId) {
        const citySelect = document.getElementById(cityFieldId);
        if (!citySelect) return;
        
        // Ensure select has proper attributes
        citySelect.setAttribute('dir', 'ltr');
        citySelect.setAttribute('lang', 'en');
        
        // Clear existing options
        citySelect.innerHTML = '<option value="">Select Country First</option>';
        
        if (!country) return;
        
        try {
            if (useBundledCountriesCities()) {
                const map = bundledCountriesMap();
                var cities = (map && map[country]) ? map[country] : [];
                if ((!cities || !cities.length) && country) {
                    cities = fallbackCitiesForCountry(country);
                }
                if (Array.isArray(cities) && cities.length > 0) {
                    citySelect.innerHTML = '<option value="">Select City</option>';
                    cities.forEach(function(city) {
                        const option = document.createElement('option');
                        option.value = city;
                        option.textContent = city;
                        citySelect.appendChild(option);
                    });
                    return;
                }
                // Bundled map missing this country — fall through to API
            }
            const apiRoot = ratibApiRoot();
            if (!apiRoot) {
                console.warn('[HR] No API base for cities; country=', country);
                return;
            }
            const url = `${apiRoot}/admin/get_countries_cities.php?action=cities&country=${encodeURIComponent(country)}`;

            const response = await fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            var cityList = (data.success && Array.isArray(data.cities)) ? data.cities.slice() : [];
            if (cityList.length === 0) {
                cityList = fallbackCitiesForCountry(country);
                if (!cityList.length && data.success) {
                    console.warn('[HR] No cities for "' + country + '" in DB or bundled data.');
                }
            }
            if (cityList.length > 0) {
                citySelect.innerHTML = '<option value="">Select City</option>';
            }
            cityList.forEach(function(city) {
                const option = document.createElement('option');
                option.value = city;
                option.textContent = city;
                citySelect.appendChild(option);
            });
        } catch (error) {
            console.error('Failed to load cities:', error);
            var fb = fallbackCitiesForCountry(country);
            if (fb.length) {
                citySelect.innerHTML = '<option value="">Select City</option>';
                fb.forEach(function(city) {
                    var opt = document.createElement('option');
                    opt.value = city;
                    opt.textContent = city;
                    citySelect.appendChild(opt);
                });
            }
        }
    }
    
    // Populate country dropdown when it exists from API (single in-flight fetch — avoids triple Network tab noise)
    async function populateCountryDropdown() {
        const countrySelect = document.getElementById('country');
        if (!countrySelect) return;

        countrySelect.setAttribute('dir', 'ltr');
        countrySelect.setAttribute('lang', 'en');

        if (countrySelect.options.length > 1) return;

        if (useBundledCountriesCities()) {
            try {
                const map = bundledCountriesMap();
                if (map && typeof map === 'object') {
                    const firstOption = countrySelect.querySelector('option[value=""]');
                    countrySelect.innerHTML = '';
                    if (firstOption) {
                        countrySelect.appendChild(firstOption);
                    }
                    Object.keys(map).sort().forEach(function(country) {
                        const option = document.createElement('option');
                        option.value = country;
                        option.textContent = country;
                        countrySelect.appendChild(option);
                    });
                }
            } catch (e) {
                console.error('Failed to load bundled countries:', e);
            }
            return;
        }

        if (hrCountriesListFetch) {
            await hrCountriesListFetch;
            return;
        }

        hrCountriesListFetch = (async function() {
            try {
                const sel = document.getElementById('country');
                if (!sel || sel.options.length > 1) return;

                const apiRoot = ratibApiRoot();
                if (!apiRoot) return;

                const url = apiRoot + '/admin/get_countries_cities.php?action=countries&_t=' + Date.now();

                const response = await fetch(url, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    cache: 'no-cache'
                });

                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }

                const data = await response.json();
                const sel2 = document.getElementById('country');
                if (!sel2 || sel2.options.length > 1) return;

                if (data.success && Array.isArray(data.countries) && data.countries.length > 0) {
                    const firstOption = sel2.querySelector('option[value=""]');
                    sel2.innerHTML = '';
                    if (firstOption) {
                        sel2.appendChild(firstOption);
                    }
                    data.countries.sort().forEach(function(country) {
                        const option = document.createElement('option');
                        option.value = country;
                        option.textContent = country;
                        sel2.appendChild(option);
                    });
                } else {
                    console.warn('[HR] No countries returned from API or API returned error');
                    const firstOption = sel2.querySelector('option[value=""]');
                    sel2.innerHTML = '';
                    if (firstOption) {
                        sel2.appendChild(firstOption);
                    }
                }
            } catch (error) {
                console.error('Failed to load countries:', error);
            } finally {
                hrCountriesListFetch = null;
            }
        })();

        await hrCountriesListFetch;
    }
    
    // Debounced: timer + shown.bs.modal + mutation observer often fire together on HR
    setTimeout(schedulePopulateCountryDropdown, 500);

    const hrModal = document.getElementById('hrModal');
    if (hrModal) {
        hrModal.addEventListener('shown.bs.modal', function() {
            schedulePopulateCountryDropdown();
        });
    }

    const modalBody = document.getElementById('hrModalBody');
    if (modalBody) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length > 0 && document.getElementById('country')) {
                    schedulePopulateCountryDropdown();
                }
            });
        });
        
        observer.observe(modalBody, {
            childList: true,
            subtree: true
        });
    }
    
    // Delegated listener: survives employeeForm cloneNode (hr.js) which drops direct listeners
    var delegateHost = document.getElementById('hrModal') || document.body;
    if (delegateHost && !delegateHost.__ratibHrCountryDelegated) {
        delegateHost.__ratibHrCountryDelegated = true;
        delegateHost.addEventListener('change', function(ev) {
            var t = ev.target;
            if (!t || t.id !== 'country') return;
            if (!t.closest || !t.closest('#employeeForm')) return;
            var cityFieldId = t.getAttribute('data-city-field') || 'city';
            if (t.getAttribute('data-action') === 'load-cities' || document.getElementById(cityFieldId)) {
                loadCitiesByCountry(t.value, cityFieldId);
            }
        });
    }

    // Make function available globally
    window.loadCitiesByCountry = loadCitiesByCountry;
    window.populateCountryDropdown = populateCountryDropdown;
});
