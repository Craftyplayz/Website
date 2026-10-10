import { OnlineController } from './application/online-controller.js';
import { createApiClient } from './services/api-client.js';
import { OnlineView } from './ui/online-view.js';

const view = new OnlineView(document);
const controller = new OnlineController(view, { api: createApiClient() });
view.controller = controller;
controller.init();
