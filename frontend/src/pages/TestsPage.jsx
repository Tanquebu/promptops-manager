import React, { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import api from '../axios';
import Layout from '../components/Layout';

const STATUS_COLORS = {
  pending: 'bg-gray-100 text-gray-600',
  running: 'bg-blue-100 text-blue-700',
  passed: 'bg-green-100 text-green-700',
  failed: 'bg-red-100 text-red-700',
  error: 'bg-orange-100 text-orange-700',
};

const ASSERTION_TYPES = ['contains', 'not_contains', 'regex', 'llm_judge'];

export default function TestsPage() {
  const { slug } = useParams();
  const [testCases, setTestCases] = useState([]);
  const [runs, setRuns] = useState({});
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({
    name: '', description: '', input_variables: '{}',
    expected_output: '', assertion_type: 'contains',
  });
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState('');

  useEffect(() => {
    api.get(`/prompts/${slug}/test-cases`).then((res) => {
      setTestCases(res.data.data);
    }).finally(() => setLoading(false));
  }, [slug]);

  const runTest = async (testCaseId) => {
    setRuns((prev) => ({ ...prev, [testCaseId]: { status: 'pending' } }));
    const res = await api.post(`/test-cases/${testCaseId}/run`);
    const runId = res.data.data.id;
    pollRun(testCaseId, runId);
  };

  const pollRun = (testCaseId, runId) => {
    const interval = setInterval(async () => {
      const res = await api.get(`/test-runs/${runId}`);
      const run = res.data.data;
      setRuns((prev) => ({ ...prev, [testCaseId]: run }));
      if (!['pending', 'running'].includes(run.status)) {
        clearInterval(interval);
      }
    }, 2000);
  };

  const deleteCase = async (id) => {
    if (!confirm('Delete this test case?')) return;
    await api.delete(`/test-cases/${id}`);
    setTestCases((prev) => prev.filter((tc) => tc.id !== id));
  };

  const handleCreate = async (e) => {
    e.preventDefault();
    setFormError('');
    setSaving(true);
    try {
      let inputVars;
      try {
        inputVars = JSON.parse(form.input_variables);
      } catch {
        setFormError('Input variables must be valid JSON, e.g. {"text": "hello"}');
        setSaving(false);
        return;
      }
      const res = await api.post(`/prompts/${slug}/test-cases`, {
        ...form,
        input_variables: inputVars,
      });
      setTestCases((prev) => [...prev, res.data.data]);
      setShowForm(false);
      setForm({ name: '', description: '', input_variables: '{}', expected_output: '', assertion_type: 'contains' });
    } catch (err) {
      const details = err.response?.data?.error?.details;
      setFormError(details ? Object.values(details).flat().join(' ') : err.response?.data?.error?.message || 'Failed.');
    } finally {
      setSaving(false);
    }
  };

  if (loading) return <Layout><p className="text-gray-500">Loading…</p></Layout>;

  return (
    <Layout>
      <div className="mb-6 flex items-start justify-between">
        <div>
          <p className="text-sm text-gray-400 font-mono">{slug}</p>
          <h1 className="text-2xl font-bold text-gray-900">Tests</h1>
        </div>
        <div className="flex gap-2">
          <Link to={`/prompts/${slug}`} className="text-sm text-gray-600 hover:text-gray-900 px-3 py-2">
            ← Back to prompt
          </Link>
          <button
            onClick={() => setShowForm(!showForm)}
            className="text-sm bg-indigo-600 text-white rounded px-4 py-2 hover:bg-indigo-700"
          >
            {showForm ? 'Cancel' : 'New Test Case'}
          </button>
        </div>
      </div>

      {showForm && (
        <form onSubmit={handleCreate} className="bg-white border border-gray-200 rounded-lg p-6 mb-6 space-y-4">
          <h3 className="text-sm font-semibold text-gray-700">New test case</h3>
          {formError && <div className="p-3 bg-red-50 text-red-700 text-sm rounded">{formError}</div>}
          <Field label="Name">
            <input required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })}
              className={inputCls} />
          </Field>
          <Field label="Input variables" hint='JSON object, e.g. {"text": "hello", "language": "English"}'>
            <textarea value={form.input_variables} onChange={(e) => setForm({ ...form, input_variables: e.target.value })}
              rows={3} className={`${inputCls} font-mono`} />
          </Field>
          <Field label="Expected output">
            <input required value={form.expected_output} onChange={(e) => setForm({ ...form, expected_output: e.target.value })}
              className={inputCls} />
          </Field>
          <Field label="Assertion type">
            <select value={form.assertion_type} onChange={(e) => setForm({ ...form, assertion_type: e.target.value })}
              className={inputCls}>
              {ASSERTION_TYPES.map((t) => <option key={t}>{t}</option>)}
            </select>
          </Field>
          <button type="submit" disabled={saving}
            className="bg-indigo-600 text-white rounded px-4 py-2 text-sm font-medium hover:bg-indigo-700 disabled:opacity-50">
            {saving ? 'Saving…' : 'Create Test Case'}
          </button>
        </form>
      )}

      {testCases.length === 0 ? (
        <p className="text-gray-500">No test cases yet.</p>
      ) : (
        <div className="space-y-4">
          {testCases.map((tc) => {
            const run = runs[tc.id];
            return (
              <div key={tc.id} className="bg-white border border-gray-200 rounded-lg p-5">
                <div className="flex items-start justify-between">
                  <div>
                    <p className="font-medium text-gray-900">{tc.name}</p>
                    {tc.description && <p className="text-sm text-gray-500">{tc.description}</p>}
                    <p className="text-xs text-gray-400 mt-1">
                      <span className="font-mono">{tc.assertion_type}</span>
                      {' · '}expected: <span className="font-mono">{tc.expected_output}</span>
                    </p>
                  </div>
                  <div className="flex gap-2">
                    <button
                      onClick={() => runTest(tc.id)}
                      disabled={run?.status === 'pending' || run?.status === 'running'}
                      className="text-sm bg-gray-800 text-white rounded px-3 py-1.5 hover:bg-gray-900 disabled:opacity-40"
                    >
                      {run?.status === 'running' ? 'Running…' : run?.status === 'pending' ? 'Queued…' : 'Run'}
                    </button>
                    <button
                      onClick={() => deleteCase(tc.id)}
                      className="text-sm text-red-500 hover:text-red-700 px-2"
                    >
                      Delete
                    </button>
                  </div>
                </div>

                {run && (
                  <div className="mt-4 border-t border-gray-100 pt-3">
                    <span className={`text-xs rounded px-2 py-0.5 font-medium ${STATUS_COLORS[run.status]}`}>
                      {run.status}
                    </span>
                    {run.llm_response && (
                      <div className="mt-2">
                        <p className="text-xs text-gray-400 mb-1">LLM response:</p>
                        <pre className="text-sm text-gray-700 bg-gray-50 rounded p-3 whitespace-pre-wrap font-mono">
                          {run.llm_response}
                        </pre>
                      </div>
                    )}
                    {run.evaluation_result?.reason && (
                      <p className="text-sm text-gray-600 italic mt-2">
                        "{run.evaluation_result.reason}"
                      </p>
                    )}
                    {run.error_message && (
                      <p className="text-sm text-red-600 mt-2">{run.error_message}</p>
                    )}
                  </div>
                )}
              </div>
            );
          })}
        </div>
      )}
    </Layout>
  );
}

const inputCls = 'w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500';

function Field({ label, children, hint }) {
  return (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
      {children}
      {hint && <p className="text-xs text-gray-400 mt-1">{hint}</p>}
    </div>
  );
}
