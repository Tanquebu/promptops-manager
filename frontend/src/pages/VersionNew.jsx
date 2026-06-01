import React, { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import api from '../axios';
import Layout from '../components/Layout';

export default function VersionNew() {
  const { slug } = useParams();
  const navigate = useNavigate();
  const [content, setContent] = useState('');
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setSaving(true);
    try {
      await api.post(`/prompts/${slug}/versions`, { content });
      navigate(`/prompts/${slug}`);
    } catch (err) {
      setError(err.response?.data?.error?.message || 'Failed to create version.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Layout>
      <div className="max-w-2xl">
        <div className="mb-6">
          <p className="text-sm text-gray-500 font-mono">{slug}</p>
          <h1 className="text-2xl font-bold text-gray-900">New Version</h1>
        </div>

        {error && (
          <div className="mb-4 p-3 rounded bg-red-50 text-red-700 text-sm">{error}</div>
        )}

        <form onSubmit={handleSubmit} className="space-y-4 bg-white rounded-lg border border-gray-200 p-6">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Prompt content
              <span className="ml-1 text-gray-400 font-normal">
                — use {`{{variable_name}}`} for variables
              </span>
            </label>
            <textarea
              required
              value={content}
              onChange={(e) => setContent(e.target.value)}
              rows={12}
              className="w-full border border-gray-300 rounded px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500"
              placeholder="Summarize the following text in {{max_words}} words or fewer. Respond in {{language}}.&#10;&#10;Text:&#10;{{text}}"
            />
          </div>
          <div className="flex gap-3 pt-2">
            <button
              type="submit"
              disabled={saving}
              className="bg-indigo-600 text-white rounded px-4 py-2 text-sm font-medium hover:bg-indigo-700 disabled:opacity-50"
            >
              {saving ? 'Saving…' : 'Save as Draft'}
            </button>
            <button
              type="button"
              onClick={() => navigate(`/prompts/${slug}`)}
              className="text-sm text-gray-600 hover:text-gray-900 px-4 py-2"
            >
              Cancel
            </button>
          </div>
        </form>
      </div>
    </Layout>
  );
}
