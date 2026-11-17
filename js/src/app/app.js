import { createApp } from 'vue'
import router from './router.js'
import App from './App.vue'
// Styles for the toasts of showError() and the other @nextcloud/dialogs helpers
import '@nextcloud/dialogs/style.css'

createApp(App)
	.use(router)
	.mount('#content-vue')
