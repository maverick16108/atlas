import { createI18n } from 'vue-i18n'
import ru from './locales/ru.json'
import en from './locales/en.json'

const i18n = createI18n({
    legacy: false, // Use Composition API
    locale: 'ru', // Default locale
    fallbackLocale: 'en',
    messages: {
        ru,
        en
    }
})

export default i18n
