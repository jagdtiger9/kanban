import { createApp, defineComponent } from 'vue'
import { createRouter, createWebHashHistory } from 'vue-router'
import App from './App.vue'
import './style.css'

// The selected node lives in the URL (#/node/<id>) so browser Back/Forward work.
// App reads the route itself; routes render nothing.
const Empty = defineComponent({ render: () => null })
const router = createRouter({
    history: createWebHashHistory(),
    routes: [
        { path: '/', component: Empty },
        { path: '/node/:id', component: Empty },
        { path: '/:pathMatch(.*)*', redirect: '/' },
    ],
})

createApp(App).use(router).mount('#app')
