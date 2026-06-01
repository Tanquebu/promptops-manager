import React from 'react';
import { BrowserRouter, Route, Routes } from 'react-router-dom';
import { AuthProvider } from './contexts/AuthContext';
import ProtectedRoute from './components/ProtectedRoute';
import Login from './pages/Login';
import PromptList from './pages/PromptList';
import PromptNew from './pages/PromptNew';
import PromptDetail from './pages/PromptDetail';
import VersionNew from './pages/VersionNew';
import TestsPage from './pages/TestsPage';

export default function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          <Route path="/login" element={<Login />} />
          <Route
            path="/prompts"
            element={<ProtectedRoute><PromptList /></ProtectedRoute>}
          />
          <Route
            path="/prompts/new"
            element={<ProtectedRoute><PromptNew /></ProtectedRoute>}
          />
          <Route
            path="/prompts/:slug"
            element={<ProtectedRoute><PromptDetail /></ProtectedRoute>}
          />
          <Route
            path="/prompts/:slug/versions/new"
            element={<ProtectedRoute><VersionNew /></ProtectedRoute>}
          />
          <Route
            path="/prompts/:slug/tests"
            element={<ProtectedRoute><TestsPage /></ProtectedRoute>}
          />
          <Route path="*" element={<ProtectedRoute><PromptList /></ProtectedRoute>} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  );
}
