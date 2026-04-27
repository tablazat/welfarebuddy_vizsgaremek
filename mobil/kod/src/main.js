import { createApp } from 'vue'
import './style.css'
import './styles/skins.css'
import router from "./router"
import i18n from "./lib/i18n"
import App from './App.vue'
import { initTheme } from "./composables/useTheme"

initTheme()
const app = createApp(App).use(router).use(i18n)
app.config.errorHandler = (err, _instance, info) => {
  console.error("[Vue]", info, err)
}
app.mount("#app")
