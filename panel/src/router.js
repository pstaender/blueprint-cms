import { createRouter, createWebHistory } from "vue-router";
import SiteView from "./views/SiteView.vue";
import PageView from "./views/PageView.vue";
import UsersView from "./views/UsersView.vue";
import UserView from "./views/UserView.vue";
import FileView from "./views/FileView.vue";
import CollectionView from "./views/CollectionView.vue";
import RecordView from "./views/RecordView.vue";

export default createRouter({
  history: createWebHistory("/panel/"),
  routes: [
    { path: "/", redirect: "/site" },
    { path: "/site", component: SiteView },
    // page ids are slug paths, subresources start with ~ (not allowed in slugs): /pages/blog/newest_entry/~files/cover.jpg
    { path: "/pages/:id(.+)/~files/:filename", component: FileView, props: true },
    { path: "/pages/:id(.+)", component: PageView, props: true },
    { path: "/users", component: UsersView },
    { path: "/users/:uid", component: UserView, props: true },
    {
      path: "/models/:name",
      component: CollectionView,
      props: true,
      // the list stays when another record is opened (see App.vue)
      meta: { key: (route) => `/models/${route.params.name}` },
      children: [
        { path: "new", component: RecordView, props: (route) => ({ name: route.params.name }) },
        { path: ":uid", component: RecordView, props: true },
      ],
    },
    { path: "/:pathMatch(.*)*", redirect: "/site" },
  ],
});
