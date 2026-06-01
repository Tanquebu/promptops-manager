import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../axios';
import Layout from '../components/Layout';

export default function PromptNew() {
  const navigate = useNavigate();
  const [form, setForm] = useState({ slug: '', name: '', description: '', variables: '' });
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setSaving(true);
    try {
      const variables = form.variables
        ? form.variables.split(',').map((v) => v.trim()).filter(Boolean)
        : [];
      await api.post('/prompts', { ...form, variables });
      navigate('/prompts');
    } catch (err) {
      const msg = err.response?.data?.error?.message || 'Failed to create prompt.';
      const details = err.response?.data?.error?.details;
      setError(details ? Object.values(details).flat().join(' ') : msg);
    } finally {
      setSaving(false);
    }
  };

  return (
    <Layout>
      <div className="max-w-lg">
        <h1 className="text-2xl font-bold text-gray-900 mb-6">New Prompt</h1>

        {error && (
          <div className="mb-4 p-3 rounded bg-red-50 text-red-700 text-sm">{error}</div>
        )}

        <form onSubmit={handleSubmit} className="space-y-4 bg-white rounded-lg border border-gray-200 p-6">
          <Field label="Slug" hint="Lowercase letters, numbers, hyphens">
            <input
              required
              pattern="[a-z0-9\-]+"
              value={form.slug}
              onChange={(e) => setForm({ ...form, slug: e.target.value })}
              className={inputCls}
              placeholder="summarize-text"
            />
          </Field>
          <Field label="Name">
            <input
              required
              value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
              className={inputCls}
              placeholder="Summarize Text"
            />
          </Field>
          <Field label="Description" optional>
            <textarea
              value={form.description}
              onChange={(e) => setForm({ ...form, description: e.target.value })}
              className={inputCls}
              rows={3}
              placeholder="What does this prompt do?"
            />
          </Field>
          <Field label="Variables" hint="Comma-separated, e.g. text, language" optional>
            <input
              value={form.variables}
              onChange={(e) => setForm({ ...form, variables: e.target.value })}
              className={inputCls}
              placeholder="text, max_words, language"
            />
          </Field>
          <div className="flex gap-3 pt-2">
            <button
              type="submit"
              disabled={saving}
              className="bg-indigo-600 text-white rounded px-4 py-2 text-sm font-medium hover:bg-indigo-700 disabled:opacity-50"
            >
              {saving ? 'Creating…' : 'Create Prompt'}
            </button>
            <button
              type="button"
              onClick={() => navigate('/prompts')}
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

const inputCls = 'w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500';

function Field({ label, children, hint, optional }) {
  return (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">
        {label}
        {optional && <span className="ml-1 text-gray-400 font-normal">(optional)</span>}
      </label>
      {children}
      {hint && <p className="text-xs text-gray-400 mt-1">{hint}</p>}
    </div>
  );
}
