{{--
    Pinpoint - Google Maps Location Picker for Filament 4

    A custom Filament form field with Google Maps integration.
    Features: Search, draggable marker, reverse geocoding, current location.

    @author Fahiem
    @version 1.0.0
    @package fahiem/filament-pinpoint
--}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @php
        $statePath = $getStatePath();
        $defaultLat = $getDefaultLat();
        $defaultLng = $getDefaultLng();
        $defaultZoom = $getDefaultZoom();
        $height = $getHeight();
        $isDraggable = $isDraggable();
        $isSearchable = $isSearchable();
        $latField = $getLatField();
        $lngField = $getLngField();
        $addressField = $getAddressField();
        $villageField = $getVillageField();
        $apiKey = $getApiKey();

        $state = $getState();
        $currentLat = $state['lat'] ?? $defaultLat;
        $currentLng = $state['lng'] ?? $defaultLng;
    @endphp

    <div
        wire:ignore
        x-data="{
            map: null,
            marker: null,
            searchBox: null,
            lat: @js($currentLat),
            lng: @js($currentLng),
            defaultLat: @js($defaultLat),
            defaultLng: @js($defaultLng),
            defaultZoom: @js($defaultZoom),
            isDraggable: @js($isDraggable),
            isSearchable: @js($isSearchable),
            latField: @js($latField),
            lngField: @js($lngField),
            addressField: @js($addressField),
            villageField: @js($villageField),
            isMapLoaded: false,

            init() {
                this.loadGoogleMaps();
            },

            loadGoogleMaps() {
                if (window.google && window.google.maps) {
                    this.initMap();
                    return;
                }

                if (window.googleMapsLoading) {
                    window.googleMapsCallbacks = window.googleMapsCallbacks || [];
                    window.googleMapsCallbacks.push(() => this.initMap());
                    return;
                }

                window.googleMapsLoading = true;
                window.googleMapsCallbacks = [];

                const apiKey = '{{ $apiKey }}';
                if (!apiKey) {
                    console.error('Google Maps API key is not configured. Please set GOOGLE_MAPS_API_KEY in your .env file.');
                    return;
                }

                const script = document.createElement('script');
                script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=places&callback=googleMapsCallback`;
                script.async = true;
                script.defer = true;

                window.googleMapsCallback = () => {
                    window.googleMapsLoading = false;
                    this.initMap();
                    window.googleMapsCallbacks.forEach(cb => cb());
                    window.googleMapsCallbacks = [];
                };

                document.head.appendChild(script);
            },

            initMap() {
                const mapElement = this.$refs.map;
                if (!mapElement) return;

                this.map = new google.maps.Map(mapElement, {
                    center: { lat: this.lat, lng: this.lng },
                    zoom: this.defaultZoom,
                    mapTypeControl: true,
                    streetViewControl: false,
                    fullscreenControl: true,
                });

                this.marker = new google.maps.Marker({
                    position: { lat: this.lat, lng: this.lng },
                    map: this.map,
                    draggable: this.isDraggable,
                    animation: google.maps.Animation.DROP,
                });

                if (this.isDraggable) {
                    this.marker.addListener('dragend', (event) => {
                        this.updatePosition(event.latLng.lat(), event.latLng.lng());
                    });
                }

                this.map.addListener('click', (event) => {
                    this.marker.setPosition(event.latLng);
                    this.updatePosition(event.latLng.lat(), event.latLng.lng());
                });

                if (this.isSearchable) {
                    this.initSearchBox();
                }

                this.isMapLoaded = true;
            },

            initSearchBox() {
                const input = this.$refs.searchInput;
                if (!input) return;

                this.searchBox = new google.maps.places.SearchBox(input);

                this.map.addListener('bounds_changed', () => {
                    this.searchBox.setBounds(this.map.getBounds());
                });

                this.searchBox.addListener('places_changed', () => {
                    const places = this.searchBox.getPlaces();
                    if (places.length === 0) return;

                    const place = places[0];
                    if (!place.geometry || !place.geometry.location) return;

                    const location = place.geometry.location;
                    this.marker.setPosition(location);
                    this.map.setCenter(location);
                    this.map.setZoom(17);

                    this.updatePosition(location.lat(), location.lng());
                });
            },

            updatePosition(lat, lng) {
                this.lat = parseFloat(lat.toFixed(7));
                this.lng = parseFloat(lng.toFixed(7));

                // Set ke form data Filament (data.fieldName)
                if (this.latField) {
                    $wire.set('data.' + this.latField, this.lat);
                }
                if (this.lngField) {
                    $wire.set('data.' + this.lngField, this.lng);
                }

                // Reverse geocoding untuk dapat alamat
                this.reverseGeocode(lat, lng);
            },

            reverseGeocode(lat, lng) {
                const geocoder = new google.maps.Geocoder();
                const latlng = { lat: parseFloat(lat), lng: parseFloat(lng) };

                geocoder.geocode({ location: latlng }, (results, status) => {
                    if (status === 'OK' && results[0]) {
                        const address = results[0].formatted_address;

                        // Update search input di map picker
                        if (this.$refs.searchInput) {
                            this.$refs.searchInput.value = address;
                        }

                        // Update alamat field jika di-set
                        if (this.addressField) {
                            $wire.set('data.' + this.addressField, address);
                        }

                        // Coba extract district/village dari address components
                        const components = results[0].address_components;
                        let village = '';

                        components.forEach(component => {
                            // Desa/Kelurahan biasanya di administrative_area_level_4 atau sublocality
                            if (component.types.includes('administrative_area_level_4') ||
                                component.types.includes('sublocality_level_1')) {
                                village = component.long_name.replace(/^(Desa|Kelurahan)\s*/i, '');
                            }
                        });

                        // Update village jika ditemukan dan field di-set
                        if (village && this.villageField) {
                            $wire.set('data.' + this.villageField, village);
                        }
                    }
                });
            },

            getCurrentLocation() {
                if (!navigator.geolocation) {
                    alert('Geolocation is not supported by this browser');
                    return;
                }

                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        const location = new google.maps.LatLng(lat, lng);

                        this.marker.setPosition(location);
                        this.map.setCenter(location);
                        this.map.setZoom(17);
                        this.updatePosition(lat, lng);
                    },
                    (error) => {
                        console.error('Error getting location:', error);
                        alert('Failed to get location: ' + error.message);
                    },
                    { enableHighAccuracy: true, timeout: 10000 }
                );
            }
        }"
        x-init="init()"
        class="fi-fo-pinpoint"
    >
        {{-- Search Box --}}
        @if ($isSearchable)
            <div style="position: relative; margin-bottom: 12px;">
                <div style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); pointer-events: none;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px; color: #9ca3af;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                </div>
                <input
                    type="text"
                    x-ref="searchInput"
                    placeholder="Search for a location..."
                    style="display: block; width: 100%; padding: 10px 16px 10px 40px; font-size: 14px; border: 1px solid #d1d5db; border-radius: 8px; background-color: white; outline: none;"
                    onfocus="this.style.borderColor='#6366f1'; this.style.boxShadow='0 0 0 2px rgba(99, 102, 241, 0.2)';"
                    onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none';"
                />
            </div>
        @endif

        {{-- Map Container --}}
        <div style="position: relative; border-radius: 8px; overflow: hidden; border: 1px solid #d1d5db;">
            <div
                x-ref="map"
                style="height: {{ $height }}px; width: 100%; background-color: #f3f4f6;"
            >
                <div x-show="!isMapLoaded" style="display: flex; align-items: center; justify-content: center; height: 100%;">
                    <div style="display: flex; align-items: center; gap: 8px; color: #6b7280;">
                        <svg style="animation: spin 1s linear infinite; width: 20px; height: 20px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Loading map...</span>
                    </div>
                </div>
            </div>

            {{-- Get Current Location Button --}}
            <button
                type="button"
                x-on:click="getCurrentLocation()"
                x-show="isMapLoaded"
                style="position: absolute; bottom: 16px; right: 16px; background-color: white; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); padding: 10px; border: none; cursor: pointer;"
                title="Use my location"
                onmouseover="this.style.backgroundColor='#f9fafb';"
                onmouseout="this.style.backgroundColor='white';"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px; color: #4f46e5;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                </svg>
            </button>
        </div>

        {{-- Coordinates Display --}}
        <div style="display: flex; align-items: center; gap: 16px; font-size: 14px; color: #4b5563; background-color: #f9fafb; border-radius: 8px; padding: 8px 16px; margin-top: 12px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-weight: 500;">Lat:</span>
                <span x-text="lat" style="font-family: monospace;"></span>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-weight: 500;">Lng:</span>
                <span x-text="lng" style="font-family: monospace;"></span>
            </div>
        </div>

        {{-- Helper Text --}}
        @if ($isDraggable)
            <p style="font-size: 12px; color: #6b7280; margin-top: 8px; display: flex; align-items: center; gap: 4px;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 16px; height: 16px; flex-shrink: 0;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                </svg>
                Click on the map or drag the marker to set the location. Use the search box to find an address.
            </p>
        @endif

    </div>

    <style>
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</x-dynamic-component>
