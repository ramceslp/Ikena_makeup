import type { CapacitorConfig } from '@capacitor/cli'

const config: CapacitorConfig = {
  appId: 'com.ikenamakeup.app',
  appName: 'Ikena Makeup',
  webDir: 'dist',
  // Served page must be http:// (not the https:// default) so the WebView's
  // own mixed-content policy doesn't block XHR/fetch to the cleartext dev
  // backend (10.0.2.2) that network_security_config.xml explicitly permits.
  // Does not affect production: an http-scheme page can still call an
  // https:// API without triggering mixed-content (that direction is safe).
  server: {
    androidScheme: 'http',
  },
}

export default config
