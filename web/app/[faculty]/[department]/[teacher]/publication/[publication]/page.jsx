'use client';

import { useState, useEffect } from "react";
import Link from "next/link";
import { lodash as _ } from "lodash";

export default function PublicationDetailPage({ params }) {
  const { faculty, department: deptCode, teacher: teacherWebpage, publication: pubSlug } = params;

  const [teacher, setTeacher] = useState(null);
  const [publication, setPublication] = useState(null);
  const [loading, setLoading] = useState(true);

  // Simple slugify function
  const slugify = (text) => {
    return text
      .toString()
      .toLowerCase()
      .replace(/\s+/g, '-')           // Replace spaces with -
      .replace(/[^\w\-]+/g, '')       // Remove all non-word chars
      .replace(/\-\-+/g, '-')         // Replace multiple - with single -
      .replace(/^-+/, '')             // Trim - from start
      .replace(/-+$/, '');            // Trim - from end
  };

  useEffect(() => {
    fetch(`/api/v1/teachers/${teacherWebpage}`)
      .then(res => res.json())
      .then(data => {
        setTeacher(data);
        
        // Find matching publication by slug
        const pub = (data.publications || []).find(p => 
          slugify(p.title) === pubSlug || p.id.toString() === pubSlug
        );
        
        setPublication(pub);
        setLoading(false);
      })
      .catch(err => {
        console.error(err);
        setLoading(false);
      });
  }, [teacherWebpage, pubSlug]);

  if (loading) {
    return (
      <div className="container mx-auto py-20 text-center text-sm font-semibold text-gray-400">
        Loading Publication Details...
      </div>
    );
  }

  if (!publication || !teacher) {
    return (
      <div className="container mx-auto py-20 text-center">
        <h2 className="text-xl font-bold">Publication Not Found</h2>
        <Link href={`/${faculty}/${deptCode}/${teacherWebpage}`} className="text-blue-600 hover:underline mt-4 inline-block">Back to Profile</Link>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-slate-50 py-12">
      <div className="container mx-auto max-w-5xl px-4">
        
        <!-- Breadcrumbs -->
        <div class="text-xs text-gray-500 font-semibold mb-8 flex flex-wrap items-center gap-2">
            <Link href="/" className="hover:text-blue-600 transition">Home</Link>
            <span>/</span>
            <Link href={`/${faculty}`} className="hover:text-blue-600 transition uppercase">{faculty}</Link>
            <span>/</span>
            <Link href={`/${faculty}/${deptCode}`} className="hover:text-blue-600 transition uppercase">{deptCode}</Link>
            <span>/</span>
            <Link href={`/${faculty}/${deptCode}/${teacherWebpage}`} className="hover:text-blue-600 transition">{teacher.first_name} {teacher.last_name}</Link>
            <span>/</span>
            <span class="text-blue-600 truncate max-w-xs">Publication Details</span>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          
          {/* Left Side: Metadata Card */}
          <div className="lg:col-span-1">
            <div className="bg-white rounded-3xl border border-gray-100 p-6 shadow-sm space-y-6">
              <h3 className="text-xs font-bold uppercase tracking-wider text-gray-400">
                Metrics
              </h3>

              <div className="space-y-4">
                {publication.journal_name && (
                  <div>
                    <p className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Journal</p>
                    <p className="text-sm font-extrabold text-gray-900 leading-snug">{publication.journal_name}</p>
                  </div>
                )}

                {publication.publication_year && (
                  <div>
                    <p className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Year</p>
                    <p class="text-sm font-extrabold text-gray-900">{publication.publication_year}</p>
                  </div>
                )}

                {publication.impact_factor && (
                  <div>
                    <p className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Impact Factor</p>
                    <p class="text-sm font-extrabold text-emerald-600">{publication.impact_factor}</p>
                  </div>
                )}

                {publication.citescore && (
                  <div>
                    <p className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">CiteScore</p>
                    <p class="text-sm font-extrabold text-blue-600">{publication.citescore}</p>
                  </div>
                )}

                {publication.h_index && (
                  <div>
                    <p className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">H-Index</p>
                    <p class="text-sm font-extrabold text-indigo-600">{publication.h_index}</p>
                  </div>
                )}
              </div>

              {publication.paper_link && (
                <div className="border-t border-gray-100 pt-6">
                  <a href={publication.paper_link} target="_blank" className="w-full py-3 bg-blue-900 hover:bg-blue-800 text-white rounded-2xl text-center font-bold text-xs uppercase tracking-wider block transition duration-300">
                    Open Source Document
                  </a>
                </div>
              )}
            </div>
          </div>

          {/* Right Side: Title/Abstract */}
          <div className="lg:col-span-2">
            <div className="bg-white rounded-3xl border border-gray-100 p-8 shadow-sm space-y-6">
              <span className="px-2.5 py-1 bg-blue-50 text-blue-800 text-[10px] font-bold rounded-lg uppercase tracking-wide">
                Research Paper
              </span>
              
              <h1 className="text-2xl sm:text-3xl font-extrabold text-gray-900 leading-snug">
                {publication.title}
              </h1>

              {publication.abstract && (
                <div className="border-t border-gray-100 pt-6">
                  <h3 className="text-sm font-bold uppercase tracking-wider text-gray-400 mb-3">
                    Abstract
                  </h3>
                  <p className="text-gray-600 text-sm leading-relaxed text-justify whitespace-pre-line">
                    {publication.abstract}
                  </p>
                </div>
              )}

              {publication.keywords && (
                <div className="border-t border-gray-100 pt-6">
                  <h3 className="text-sm font-bold uppercase tracking-wider text-gray-400 mb-3">
                    Keywords
                  </h3>
                  <div className="flex flex-wrap gap-2">
                    {publication.keywords.split(',').map((kw, i) => (
                      <span key={i} className="px-3 py-1 bg-slate-50 border border-gray-100 rounded-lg text-xs font-medium text-gray-600">
                        {kw.trim()}
                      </span>
                    ))}
                  </div>
                </div>
              )}
            </div>
          </div>

        </div>
      </div>
    </div>
  );
}
