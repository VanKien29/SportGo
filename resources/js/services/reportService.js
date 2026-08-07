import { api } from './api.js';

export const reportService = {
  list(page = 1) { return api(`/api/reports?page=${page}`); },
  show(id) { return api(`/api/reports/${id}`); },
};
