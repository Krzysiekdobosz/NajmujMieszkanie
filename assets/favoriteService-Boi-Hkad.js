import{z as t}from"./index-BaovNk5S.js";const r={list:e=>t.get("/favorites",{params:e}),add:e=>t.post("/favorites",{property_id:e}),remove:e=>t.delete(`/favorites/${e}`)};export{r as f};
