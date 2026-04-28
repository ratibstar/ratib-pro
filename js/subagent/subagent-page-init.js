/**
 * EN: Implements frontend interaction behavior in `js/subagent/subagent-page-init.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/subagent/subagent-page-init.js`.
 */
// Subagent Page Initialization Script
// Moved from inline script in pages/subagent.php

document.addEventListener('DOMContentLoaded', async function() {
    // Populate country dropdown on page load from API
    async function populateCountryDropdown() {
        const countrySelect = document.getElementById('country');
        if (!countrySelect) return;
        
        try {
            const baseUrl = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || document.getElementById('app-config')?.getAttribute('data-base-url') || '';
            const timestamp = new Date().getTime();
            const url = `${baseUrl}/api/admin/get_countries_cities.php?action=countries&_t=${timestamp}`;
            
            const response = await fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                cache: 'no-cache'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            // Only populate if countries exist in System Settings
            if (data.success && Array.isArray(data.countries) && data.countries.length > 0) {
                // Clear existing options except the first one
                const firstOption = countrySelect.querySelector('option[value=""]');
                countrySelect.innerHTML = '';
                if (firstOption) {
                    countrySelect.appendChild(firstOption);
                }
                
                // Add all countries from the API
                data.countries.sort().forEach(country => {
                    const option = document.createElement('option');
                    option.value = country;
                    option.textContent = country;
                    countrySelect.appendChild(option);
                });
            } else {
                // No countries in System Settings - keep dropdown empty
                const firstOption = countrySelect.querySelector('option[value=""]');
                countrySelect.innerHTML = '';
                if (firstOption) {
                    countrySelect.appendChild(firstOption);
                }
            }
        } catch (error) {
            console.error('Failed to load countries:', error);
        }
    }
    
    await populateCountryDropdown();
    
    // Check for edit/view parameters and open modals automatically
    const urlParams = new URLSearchParams(window.location.search);
    const editId = urlParams.get('edit');
    const viewId = urlParams.get('view');
    
    if (editId) {
        // Wait for subagentManager to be ready, then open edit modal
        setTimeout(() => {
            if (window.subagentManager && window.subagentManager.edit) {
                window.subagentManager.edit(parseInt(editId));
            }
        }, 1000);
    } else if (viewId) {
        // Wait for subagentManager to be ready, then open view modal
        setTimeout(() => {
            if (window.subagentManager && window.subagentManager.view) {
                window.subagentManager.view(parseInt(viewId));
            }
        }, 1000);
    }
});

// Function to load cities by country from API (if not already defined)
if (typeof window.loadCitiesByCountry === 'undefined') {
    window.loadCitiesByCountry = async function(country, cityFieldId) {
        const citySelect = document.getElementById(cityFieldId);
        if (!citySelect) return;
        
        // Clear existing options
        citySelect.innerHTML = '<option value="">Select Country First</option>';
        
        if (!country) return;
        
        try {
            const baseUrl = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || document.getElementById('app-config')?.getAttribute('data-base-url') || '';
            const url = `${baseUrl}/api/admin/get_countries_cities.php?action=cities&country=${encodeURIComponent(country)}`;
            
            const response = await fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success && Array.isArray(data.cities) && data.cities.length > 0) {
                data.cities.forEach(city => {
                    const option = document.createElement('option');
                    option.value = city;
                    option.textContent = city;
                    citySelect.appendChild(option);
                });
            } else {
                citySelect.innerHTML = '<option value="">No cities available for this country</option>';
            }
        } catch (error) {
            console.error('Failed to load cities:', error);
            citySelect.innerHTML = '<option value="">Error loading cities</option>';
        }
    };
}

