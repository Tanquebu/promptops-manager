import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../axios';
import Layout from '../components/Layout';

export default function PromptList() {
  const [prompts, setPrompts] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.get('/prompts').then((res) => {
      setPrompts(res.data.data);
    }).finally(() => setLoading(false));
  }, []);

  return (
    <Layout>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-2xl font-bold text-gray-900">Prompts</h1>
        <Link
          to="/prompts/new"
          className="bg-indigo-600 text-white rounded px-4 py-2 text-sm font-medium hover:bg-indigo-700"
        >
          New Prompt
        </Link>
      </div>

      {loading ? (
        <p className="text-gray-500">Loading…</p>
      ) : prompts.length === 0 ? (
        <p className="text-gray-500">No prompts yet. Create your first one.</p>
      ) : (
        <div className="space-y-3">
          {prompts.map((p) => (
            <Link
              key={p.id}
              to={`/prompts/${p.slug}`}
              className="block bg-white rounded-lg border border-gray-200 px-5 py-4 hover:border-indigo-300 transition-colors"
            >
              <div className="flex items-start justify-between">
                <div>
                  <span className="font-semibold text-gray-900">{p.name}</span>
                  <span className="ml-2 text-xs text-gray-400 font-mono">{p.slug}</span>
                </div>
                <span className="text-xs text-gray-400">
                  {new Date(p.created_at).toLocaleDateString()}
                </span>
              </div>
              {p.description && (
                <p className="text-sm text-gray-500 mt-1">{p.description}</p>
              )}
              {p.variables?.length > 0 && (
                <div className="flex gap-1 mt-2 flex-wrap">
                  {p.variables.map((v) => (
                    <span
                      key={v}
                      className="bg-gray-100 text-gray-600 text-xs rounded px-2 py-0.5 font-mono"
                    >
                      {`{{${v}}}`}
                    </span>
                  ))}
                </div>
              )}
            </Link>
          ))}
        </div>
      )}
    </Layout>
  );
}
