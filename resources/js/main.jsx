import React from 'react';
import { createRoot } from 'react-dom/client';
import { flushSync } from 'react-dom';
import '../css/style.css';
import App from './App.jsx';

flushSync(() => {
  createRoot(document.getElementById('react-root')).render(<App />);
});

await import('./legacy-emoji-picker.js');
await import('./legacy-app.js');
