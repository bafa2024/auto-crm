import React from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import Dashboard from './pages/Dashboard';
import Contacts from './pages/Contacts';

function App() {
  return (
    <Router>
      <div className="App">
        <Routes>
          <Route path="/" element={<Navigate to="/dashboard" replace />} />
          <Route path="/dashboard" element={<Dashboard />} />
          <Route path="/contacts" element={<Contacts />} />
          <Route path="/campaigns" element={<div>Campaigns Page (Coming Soon)</div>} />
          <Route path="/employees" element={<div>Employee Management (Coming Soon)</div>} />
          <Route path="/settings" element={<div>Settings (Coming Soon)</div>} />
        </Routes>
      </div>
    </Router>
  );
}

export default App; 