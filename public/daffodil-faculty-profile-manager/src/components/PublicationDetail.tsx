import React, { useState } from 'react';
import { Publication, Teacher } from '../types';
import { 
  ArrowLeft, BookOpen, Calendar, Award, Copy, CheckCircle, 
  ExternalLink, FileText, Bookmark, Quote, Info
} from 'lucide-react';

interface PublicationDetailProps {
  publication: Publication;
  authorTeacher?: Teacher;
  onBack: () => void;
}

export const PublicationDetail: React.FC<PublicationDetailProps> = ({
  publication,
  authorTeacher,
  onBack
}) => {
  const [copiedFormat, setCopiedFormat] = useState<string | null>(null);

  // Dynamic Citation Styles
  const citations = {
    APA: `${publication.authors} (${publication.year}). ${publication.title}. ${publication.venue}. ${publication.doi ? `https://doi.org/${publication.doi}` : ''}`,
    IEEE: `[1] ${publication.authors}, "${publication.title}," ${publication.venue}, ${publication.year}.${publication.doi ? ` doi: ${publication.doi}.` : ''}`,
    BibTeX: `@article{diu_${publication.id},\n  author = {${publication.authors.replace(/,/g, ' and')}},\n  title = {${publication.title}},\n  journal = {${publication.venue}},\n  year = {${publication.year}},\n  ${publication.doi ? `doi = {${publication.doi}},` : ''}\n  publisher = {${publication.publisher || 'DIU Press'}}\n}`
  };

  const handleCopy = (styleName: 'APA' | 'IEEE' | 'BibTeX', text: string) => {
    navigator.clipboard.writeText(text);
    setCopiedFormat(styleName);
    setTimeout(() => setCopiedFormat(null), 2000);
  };

  return (
    <div className="bg-white/40 backdrop-blur-md rounded-2xl border border-white/60 shadow-xs max-w-4xl mx-auto overflow-hidden font-sans" id={`publication-detail-${publication.id}`}>
      
      {/* Upper header */}
      <div className="bg-gradient-to-r from-diu-green to-diu-green-dark p-6 md:p-8 text-white relative">
        <button 
          onClick={onBack}
          className="bg-white/20 hover:bg-white/30 text-white text-xs font-semibold px-3 py-1.5 rounded-lg flex items-center gap-1.5 transition-all mb-4 backdrop-blur-xs cursor-pointer"
        >
          <ArrowLeft className="w-3.5 h-3.5" />
          Back to Profile
        </button>

        <span className="bg-diu-orange text-white text-[10px] font-sans font-bold uppercase px-2.5 py-0.5 rounded-sm tracking-wide">
          {publication.type} Publication
        </span>
        <h2 className="text-lg md:text-xl font-display font-bold text-white tracking-tight mt-3 leading-snug">
          {publication.title}
        </h2>
        <p className="text-xs text-white/85 mt-2 font-medium">Authors: {publication.authors}</p>
      </div>

      <div className="p-6 md:p-8 space-y-8">
        
        {/* Core Metadata Block */}
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 p-4 bg-white/30 backdrop-blur-xs rounded-xl border border-white/60 text-xs ring-1 ring-slate-900/5">
          <div>
            <p className="text-[10px] text-slate-400 font-bold uppercase">Journal / Conference</p>
            <p className="font-semibold text-slate-800 mt-1 leading-tight">{publication.venue}</p>
          </div>
          <div>
            <p className="text-[10px] text-slate-400 font-bold uppercase">Published Year</p>
            <p className="font-semibold text-slate-800 mt-1 flex items-center gap-1">
              <Calendar className="w-3.5 h-3.5 text-diu-green" />
              {publication.year}
            </p>
          </div>
          <div>
            <p className="text-[10px] text-slate-400 font-bold uppercase">Digital Object Identifier (DOI)</p>
            {publication.doi ? (
              <p className="font-mono font-semibold text-diu-green hover:underline mt-1 break-all">
                <a href={`https://doi.org/${publication.doi}`} target="_blank" rel="noopener noreferrer">
                  {publication.doi}
                </a>
              </p>
            ) : (
              <p className="text-slate-400 mt-1">Not available</p>
            )}
          </div>
          <div>
            <p className="text-[10px] text-slate-400 font-bold uppercase">Total Citations</p>
            <p className="font-semibold text-slate-800 mt-1 flex items-center gap-1.5">
              <Award className="w-3.5 h-3.5 text-diu-orange" />
              <span className="bg-white/60 border border-white/80 px-2 py-0.5 rounded-sm font-mono text-[11px]">
                {publication.citations || 0}
              </span>
            </p>
          </div>
        </div>

        {/* Abstract Section */}
        <div>
          <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider mb-2.5 flex items-center gap-1.5">
            <FileText className="w-4 h-4 text-diu-green" />
            Abstract
          </h3>
          <p className="text-sm text-slate-600 leading-relaxed font-sans text-justify">
            {publication.abstract || "The abstract for this scholarly publication is currently being index-linked. Please reference the official DOI above."}
          </p>
        </div>

        {/* Dynamic Citation Generator Widget */}
        <div className="bg-white/30 backdrop-blur-xs rounded-xl border border-white/60 p-5 ring-1 ring-slate-900/5">
          <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider mb-4 flex items-center gap-1.5">
            <Quote className="w-4 h-4 text-diu-orange" />
            Scholarly Citation Generator
          </h3>
          
          <div className="space-y-4">
            {/* APA */}
            <div>
              <div className="flex justify-between items-center mb-1 text-[11px] font-semibold text-gray-400">
                <span>APA STYLE</span>
                <button 
                  onClick={() => handleCopy('APA', citations.APA)}
                  className="hover:text-diu-green flex items-center gap-1 cursor-pointer transition-colors"
                >
                  {copiedFormat === 'APA' ? (
                    <span className="text-emerald-600 flex items-center gap-1 font-sans">
                      <CheckCircle className="w-3.5 h-3.5" /> Copied
                    </span>
                  ) : (
                    <span className="flex items-center gap-1 font-sans">
                      <Copy className="w-3 h-3" /> Copy Citation
                    </span>
                  )}
                </button>
              </div>
              <p className="p-3 bg-white/50 border border-white/80 rounded-lg text-xs text-slate-700 font-sans select-all leading-relaxed">
                {citations.APA}
              </p>
            </div>

            {/* IEEE */}
            <div>
              <div className="flex justify-between items-center mb-1 text-[11px] font-semibold text-slate-400">
                <span>IEEE STYLE</span>
                <button 
                  onClick={() => handleCopy('IEEE', citations.IEEE)}
                  className="hover:text-diu-green flex items-center gap-1 cursor-pointer transition-colors"
                >
                  {copiedFormat === 'IEEE' ? (
                    <span className="text-emerald-600 flex items-center gap-1 font-sans">
                      <CheckCircle className="w-3.5 h-3.5" /> Copied
                    </span>
                  ) : (
                    <span className="flex items-center gap-1 font-sans">
                      <Copy className="w-3 h-3" /> Copy Citation
                    </span>
                  )}
                </button>
              </div>
              <p className="p-3 bg-white/50 border border-white/80 rounded-lg text-xs text-slate-700 font-sans select-all leading-relaxed">
                {citations.IEEE}
              </p>
            </div>

            {/* BibTeX */}
            <div>
              <div className="flex justify-between items-center mb-1 text-[11px] font-semibold text-slate-400">
                <span>BIBTEX PARSER</span>
                <button 
                  onClick={() => handleCopy('BibTeX', citations.BibTeX)}
                  className="hover:text-diu-green flex items-center gap-1 cursor-pointer transition-colors"
                >
                  {copiedFormat === 'BibTeX' ? (
                    <span className="text-emerald-600 flex items-center gap-1 font-sans">
                      <CheckCircle className="w-3.5 h-3.5" /> Copied
                    </span>
                  ) : (
                    <span className="flex items-center gap-1 font-sans">
                      <Copy className="w-3 h-3" /> Copy BibTeX
                    </span>
                  )}
                </button>
              </div>
              <pre className="p-3 bg-slate-900 text-slate-100 rounded-lg text-[11px] font-mono select-all overflow-x-auto whitespace-pre leading-normal shadow-inner">
                {citations.BibTeX}
              </pre>
            </div>
          </div>
        </div>

        {/* Contributing Academic Member info */}
        {authorTeacher && (
          <div className="p-4 bg-white/30 border border-white/60 rounded-xl flex items-center justify-between ring-1 ring-slate-900/5">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-full overflow-hidden bg-slate-200 shrink-0">
                <img src={authorTeacher.avatar} alt={authorTeacher.name} className="w-full h-full object-cover" />
              </div>
              <div>
                <p className="text-[10px] text-slate-400 font-bold uppercase">Contributing Scholar</p>
                <p className="text-xs font-bold text-slate-800 font-display">{authorTeacher.name}</p>
              </div>
            </div>
            
            <button
              onClick={onBack}
              className="text-xs font-semibold text-diu-green hover:text-diu-orange transition-colors flex items-center gap-1 cursor-pointer"
            >
              Back to Academic Profile <ExternalLink className="w-3.5 h-3.5" />
            </button>
          </div>
        )}

      </div>
    </div>
  );
};
