'use client';

import { useState, useEffect } from "react";
import Link from "next/link";
import RoleFilterSidebar from "../../../src/components/department/RoleFilterSidebar.jsx";
import FacultyCard from "../../../src/components/department/FacultyCard.jsx";

const departmentalManagementRoles = [
  "Dean", "Associate Dean", "Head", "Associate Head", "Assistant Head",
  "Chairman", "Director", "Coordinator"
];

export default function DepartmentPage({ params }) {
  const { faculty, department: deptCode } = params;

  const [selectedRole, setSelectedRole] = useState("");
  const [searchTerm, setSearchTerm] = useState("");
  const [deptDetails, setDeptDetails] = useState(null);
  const [teachers, setTeachers] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    Promise.all([
      fetch("/api/v1/departments").then(res => res.json()),
      fetch("/api/v1/faculties").then(res => res.json()),
      fetch(`/api/v1/teachers?department_code=${deptCode.toUpperCase()}`).then(res => res.json())
    ]).then(([depts, facs, teachersList]) => {
      const activeDept = depts.find(d => d.code.toLowerCase() === deptCode.toLowerCase());
      
      if (activeDept) {
        const parentFac = facs.find(f => f.id === activeDept.faculty_id);
        setDeptDetails({
          name: activeDept.name,
          facultyFullName: parentFac ? parentFac.name : ""
        });
      }

      // Map API teacher to match React card format
      const mappedTeachers = teachersList.map(t => ({
        id: t.webpage,
        name: `${t.first_name} ${t.last_name}`,
        role: t.designation ? t.designation.name : "Faculty Member",
        email: t.secondary_email || "",
        imageUrl: t.photo || "",
        faculty: faculty,
        department: deptCode
      }));

      setTeachers(mappedTeachers);
      setLoading(false);
    }).catch(err => {
      console.error(err);
      setLoading(false);
    });
  }, [faculty, deptCode]);

  const filteredTeachers = teachers.filter(t => {
    const matchesRole = selectedRole
      ? (selectedRole === "all-management" 
         ? departmentalManagementRoles.some(roleName => t.role.toLowerCase().includes(roleName.toLowerCase())) 
         : t.role.toLowerCase() === selectedRole.toLowerCase())
      : true;

    const matchesSearch = searchTerm
      ? (t.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
         t.email.toLowerCase().includes(searchTerm.toLowerCase()))
      : true;

    return matchesRole && matchesSearch;
  });

  const managementMembers = filteredTeachers.filter(t =>
    departmentalManagementRoles.some(roleName => t.role.toLowerCase().includes(roleName.toLowerCase()))
  );

  const facultyMembersList = filteredTeachers.filter(t =>
    !departmentalManagementRoles.some(roleName => t.role.toLowerCase().includes(roleName.toLowerCase()))
  );

  if (loading) {
    return (
      <div className="container mx-auto py-20 text-center text-sm font-semibold text-gray-400">
        Loading Department Directory...
      </div>
    );
  }

  if (!deptDetails) {
    return (
      <div className="container mx-auto py-20 text-center">
        <h2 class="text-xl font-bold">Department Not Found</h2>
        <Link href="/" className="text-blue-600 hover:underline mt-4 inline-block">Back to Home</Link>
      </div>
    );
  }

  // Get active roles for sidebar filtering
  const distinctRoles = Array.from(new Set(teachers.map(t => t.role)));

  return (
    <div className="min-h-screen">
      {/* Banner */}
      <div className="relative bg-blue-900 text-white overflow-hidden py-16">
        <div className="absolute inset-0 bg-gradient-to-t from-blue-950 via-transparent to-transparent"></div>
        <div className="relative z-10 container mx-auto px-4">
          <div className="flex items-center gap-2 mb-4 text-xs font-semibold text-gray-300">
            <Link href="/" className="hover:text-white transition">Home</Link>
            <span>/</span>
            <span>{deptDetails.facultyFullName}</span>
          </div>
          <h1 className="text-3xl md:text-5xl font-extrabold text-white">{deptDetails.name}</h1>
          <p className="mt-2 text-gray-200 text-sm max-w-2xl">
            Discover our distinguished faculty members, expertise, and achievements in {deptDetails.name}.
          </p>
        </div>
      </div>

      <div className="container mx-auto py-12 px-4 md:px-0">
        <div className="flex flex-col md:flex-row gap-8">
          <div className="w-full md:w-1/3">
            <RoleFilterSidebar
              facultyRoles={distinctRoles.filter(r => !departmentalManagementRoles.some(role => r.toLowerCase().includes(role.toLowerCase())))}
              managementRoles={distinctRoles.filter(r => departmentalManagementRoles.some(role => r.toLowerCase().includes(role.toLowerCase())))}
              selectedRole={selectedRole}
              onRoleChange={setSelectedRole}
            />
          </div>

          <div className="w-full md:w-2/3 space-y-12">
            {/* Management Section */}
            {managementMembers.length > 0 && (
              <div>
                <h2 className="text-xl font-bold text-neutral-800 mb-6">Departmental Management</h2>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                  {managementMembers.map((t) => (
                    <a key={t.id} href={`/${faculty}/${deptCode}/${t.id}`}>
                      <FacultyCard faculty={t} />
                    </a>
                  ))}
                </div>
              </div>
            )}

            {managementMembers.length > 0 && facultyMembersList.length > 0 && (
              <div className="border-b border-gray-200 my-8"></div>
            )}

            {/* General Faculty Section */}
            {facultyMembersList.length > 0 && (
              <div>
                <h2 className="text-xl font-bold text-neutral-800 mb-6">Departmental Faculty Members</h2>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                  {facultyMembersList.map((t) => (
                    <a key={t.id} href={`/${faculty}/${deptCode}/${t.id}`}>
                      <FacultyCard faculty={t} />
                    </a>
                  ))}
                </div>
              </div>
            )}

            {filteredTeachers.length === 0 && (
              <div className="bg-white rounded-xl shadow-sm p-12 text-center border border-gray-200">
                <p className="text-neutral-500">No faculty members found matching filters.</p>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
