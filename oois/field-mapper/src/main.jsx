import React from "react";
import { createRoot } from "react-dom/client";
import { Capacitor } from "@capacitor/core";
import "leaflet/dist/leaflet.css";
import "./styles.css";
import App from "./App.jsx";

createRoot(document.getElementById("root")).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>,
);

if (import.meta.env.PROD && !Capacitor.isNativePlatform() && "serviceWorker" in navigator)
  navigator.serviceWorker.register("./sw.js").catch(() => {});
