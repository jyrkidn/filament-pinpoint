# Changelog

All notable changes to `filament-pinpoint` will be documented in this file.

## v1.0.3 - 2025-12-04

### Fixed

- 🐛 Map blank on edit - map now displays correctly when editing existing records
- 🐛 Address not showing in search box - address from database now displays in search input during edit mode
- 🐛 Lat/Lng not saving properly - coordinates from database (string) now correctly converted to float

### Changed

- Add `addressField` to state hydration for loading address from database on edit
- Add `parseFloat()` for lat/lng string to float conversion
- Add `x-model="address"` binding on search input for two-way data binding
- Update `reverseGeocode()` to sync address state with search box

## v1.0.2 - 2025-12-01

### Added

- ⭐ Post-install star reminder message
- 🌙 Dark mode support for all UI elements

### Changed

- Search input now adapts to light/dark theme
- Location button now adapts to light/dark theme
- Helper text now adapts to light/dark theme
- Fix homepage URL in composer.json

### Removed

- Coordinates display below map (lat/lng still saved to form fields)

## v1.0.1 - 2025-12-01

### Fixed

- Minor fixes and improvements

## v1.0.0 - 2025-12-01

### Initial Release

- 📍 Google Maps location picker for Filament 4
- 🔍 Search location using Google Places Autocomplete
- 📍 Click on map to set marker
- ✋ Draggable marker support
- 📱 Get current device location
- 🏠 Reverse geocoding to auto-fill address fields
- ⚙️ Configurable default location, zoom, and height
