'use client';

import { useState, useEffect } from "react";
import Link from "next/link";
import ProfileHeader from "../../../../src/components/faculty/ProfileHeader";
import ProfileTabs from "../../../../src/components/faculty/ProfileTabs";

export default function TeacherProfilePage({ params }) {
  const { faculty, department: deptCode, teacher: teacherWebpage } = params;

  const [teacher, setTeacher] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetch(`/api/v1/teachers/${teacherWebpage}`)
      .then(res => res.json())
      .then(data => {
        const mappedTeacher = {
          id: data.webpage,
          name: `${data.first_name} ${data.last_name}`,
          role: data.designation ? data.designation.name : "Faculty Member",
          email: data.secondary_email || "",
          imageUrl: data.photo || "",
          phone: data.phone || "N/A",
          mobile: data.personal_phone || "N/A",
          bio: data.bio || "",
          education: (data.educations || []).map(e => ({
            id: e.id,
            degree: e.degree_name,
            institution: e.institution_name,
            year: e.passing_year || "N/A",
            result: e.result || "N/A"
          })),
          research: data.research_interest || "",
          publications: (data.publications || []).map(p => ({
            id: p.id,
            title: p.title,
            journal: p.journal_name || "Journal Venue",
            year: p.publication_year || "N/A",
            link: p.paper_link || ""
          })),
          training: (data.trainingExperiences || []).map(t => ({
            id: t.id,
            title: t.title,
            institution: t.institution_name,
            duration: t.duration || ""
          })),
          awards: (data.awards || []).map(a => ({
            id: a.id,
            title: a.title,
            body: a.awarding_body || "Awarding Body",
            year: a.year || ""
          })),
          memberships: (data.memberships || []).map(m => ({
            id: m.id,
            title: m.title,
            role: m.membership_role || "Member"
          })),
          socialLinks: (data.social_links || []).map(sl => ({
            platform: sl.platform ? sl.platform.name : "Social",
            url: sl.url
          })),
          departmentData: {
            id: deptCode,
            name: data.department ? data.department.name : deptCode.toUpperCase()
          }
        };

        setTeacher(mappedTeacher);
        setLoading(false);
      })
      .catch(err => {
        console.error(err);
        setLoading(false);
      });
  }, [teacherWebpage, deptCode]);

  if (loading) {
    return (
      <div className="container mx-auto py-20 text-center text-sm font-semibold text-gray-400">
        Loading Profile Details...
      </div>
    );
  }

  if (!teacher) {
    return (
      <div className="container mx-auto py-20 text-center">
        <h2 className="text-xl font-bold">Faculty Member Not Found</h2>
        <Link href="/" className="text-blue-600 hover:underline mt-4 inline-block">Back to Home</Link>
      </div>
    );
  }

  return (
    <div className="pt-8 min-h-screen">
      <div className="container mx-auto px-4 lg:px-12 bg-white rounded-xl">
        <Link
          href={`/${faculty}/${deptCode}`}
          className="inline-flex items-center text-neutral-400 mb-6 hover:text-blue-600 transition"
        >
          &larr; Back to {teacher.departmentData.name}
        </Link>
      </div>

      <ProfileHeader faculty={teacher} />

      <div className="container mx-auto py-8 px-4 lg:px-12">
        <ProfileTabs faculty={teacher} />
      </div>
    </div>
  );
}
