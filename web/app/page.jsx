'use client';

import { useState, useEffect } from "react";
import Hero from "../src/components/home/Hero";
import FacultyFilterSidebar from "../src/components/home/FacultyFilterSidebar";
import DepartmentCard from "../src/components/home/DepartmentCard";

export default function Home({ params }) {
  const [faculties, setFaculties] = useState([]);
  const [departments, setDepartments] = useState([]);
  const [selectedFaculty, setSelectedFaculty] = useState("");
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    Promise.all([
      fetch("/api/v1/faculties").then(res => res.json()),
      fetch("/api/v1/departments").then(res => res.json())
    ]).then(([facData, deptData]) => {
      const mappedFacs = facData.map(f => ({
        id: f.short_name.toLowerCase(),
        name: f.name
      }));

      const mappedDepts = deptData.map(d => {
        const facultyObj = facData.find(f => f.id === d.faculty_id);
        return {
          id: d.code.toLowerCase(),
          name: d.name,
          faculty: facultyObj ? facultyObj.short_name.toLowerCase() : "",
          facultyFullName: facultyObj ? facultyObj.name : "",
          imageUrl: "/banner.png"
        };
      });

      setFaculties(mappedFacs);
      setDepartments(mappedDepts);
      
      if (params?.faculty) {
        setSelectedFaculty(params.faculty.toLowerCase());
      } else if (mappedFacs.length > 0) {
        setSelectedFaculty(mappedFacs[0].id);
      }
      
      setLoading(false);
    }).catch(err => {
      console.error(err);
      setLoading(false);
    });
  }, [params?.faculty]);

  const handleFacultyChange = (id) => {
    setSelectedFaculty(id);
    window.history.pushState(null, "", `/${id}`);
  };

  const filteredDepartments = departments.filter(d => 
    selectedFaculty ? d.faculty === selectedFaculty : true
  );

  if (loading) {
    return (
      <div className="container mx-auto py-20 text-center text-sm font-semibold text-gray-400">
        Loading Faculty Directory...
      </div>
    );
  }

  return (
    <div>
      <Hero />
      <div className="container mx-auto py-12 px-4 md:px-0">
        <div className="flex flex-col lg:flex-row md:gap-8">
          <div className="w-full md:w-1/2 lg:w-1/3 md:px-0">
            <FacultyFilterSidebar
              faculties={faculties}
              selectedFaculty={selectedFaculty}
              onFacultyChange={handleFacultyChange}
            />
          </div>
          <div className="w-full md:w-3/4 lg:w-4/5">
            <h2 className="text-[#2F2F2F] font-bold text-xl lg:text-4xl mb-6">
              {selectedFaculty
                ? `Faculty of ${faculties.find((f) => f.id === selectedFaculty)?.name || ""}`
                : "All Departments"}
            </h2>
            {filteredDepartments.length > 0 ? (
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                {filteredDepartments.map((dept) => (
                  <div key={dept.id}>
                    <DepartmentCard department={dept} />
                  </div>
                ))}
              </div>
            ) : (
              <div className="bg-white rounded-xl p-12 text-center border border-gray-200">
                <p className="text-gray-500">No departments found under this faculty.</p>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
