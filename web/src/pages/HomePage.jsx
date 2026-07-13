import { useState, useEffect } from "react";
import { useSearchParams } from "react-router-dom";
import { motion } from "framer-motion";

import Hero from "../components/home/Hero";
import SearchBar from "../components/home/SearchBar";
import FacultyFilterSidebar from "../components/home/FacultyFilterSidebar";
import DepartmentCard from "../components/home/DepartmentCard";

// import { faculties } from "../data/mockData";
import { faculties } from "../data/faculties";
import { departments } from "../data/department";

const HomePage = () => {
  const [searchParams, setSearchParams] = useSearchParams();
  const [selectedFaculty, setSelectedFaculty] = useState(
    searchParams.get("faculty") || ""
  );

  const [searchTerm, setSearchTerm] = useState(
    searchParams.get("search") || ""
  );

  const [filteredDepartments, setFilteredDepartments] = useState(departments);

  useEffect(() => {
    // Filter departments based on selected faculty and search term
    const filtered = departments.filter((dept) => {
      const matchesFaculty = selectedFaculty
        ? dept.faculty === selectedFaculty
        : true;

      const matchesSearch = searchTerm
        ? dept.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
          dept.facultyFullName.toLowerCase().includes(searchTerm.toLowerCase())
        : true;

      return matchesFaculty && matchesSearch;
    });

    setFilteredDepartments(filtered);

    // Update URL params
    const params = {};
    if (selectedFaculty) params.faculty = selectedFaculty;
    if (searchTerm) params.search = searchTerm;

    setSearchParams(params, { replace: true });
  }, [selectedFaculty, searchTerm, setSearchParams]);

  const handleFacultyChange = (facultyId) => {
    setSelectedFaculty(facultyId);
  };

  const handleSearch = (term) => {
    setSearchTerm(term);
  };

  return (
    <div>
      <Hero />

      <div className="container mx-auto py-12 px-4 md:px-0">
        {/* <div className="mb-10">
          <SearchBar onSearch={handleSearch} />
        </div> */}

        <div className="flex flex-col lg:flex-row md:gap-8">
          <div className="w-full md:w-1/2 lg:w-1/3 md:px-0">
            <motion.div
              initial={{ opacity: 0, y: 50 }}
              whileInView={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.6, ease: "easeOut" }}
              viewport={{ once: true }}
            >
              <FacultyFilterSidebar
                faculties={faculties}
                selectedFaculty={selectedFaculty}
                onFacultyChange={handleFacultyChange}
              />
            </motion.div>
          </div>

          <div className="w-full md:w-3/4 lg:w-4/5">
            <motion.div
              initial={{ opacity: 0, y: 50 }}
              whileInView={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.6, ease: "easeOut" }}
              viewport={{ once: true }}
            >
              <h2 className="text-[#2F2F2F] font-bold text-xl lg:text-4xl mb-6">
                {selectedFaculty
                  ? `Faculty of ${
                      faculties.find((f) => f.id === selectedFaculty).name
                    }`
                  : "All Departments"}
              </h2>
            </motion.div>

            {filteredDepartments.length > 0 ? (
              <motion.div
                className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6"
                initial={{ opacity: 0, y: 50 }}
                whileInView={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.6, ease: "easeOut" }}
                viewport={{ once: true }}
              >
                {filteredDepartments.map((department) => (
                  <DepartmentCard key={department.id} department={department} />
                ))}
              </motion.div>
            ) : (
              <motion.div
                className="bg-white rounded-xl p-6 text-center border border-gray-200"
                initial={{ opacity: 0, y: 50 }}
                whileInView={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.6, ease: "easeOut" }}
                viewport={{ once: true }}
              >
                <div className="flex flex-col items-center justify-center space-y-4">
                  <div className="bg-diuBlue rounded-full p-3 inline-block">
                    <svg
                      className="w-6 h-6 text-white"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                      xmlns="http://www.w3.org/2000/svg"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth="2"
                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                      />
                    </svg>
                  </div>
                  <p className="text-xl font-semibold text-gray-700">
                    No Departments Found
                  </p>
                  <p className="text-gray-500 max-w-3xl">
                    It seems we couldn’t find any departments matching your
                    search or filter. Try adjusting your filters or search term.
                  </p>
                  <button
                    onClick={() => {
                      setSelectedFaculty("");
                      setSearchTerm("");
                    }}
                    className="mt-4 px-6 py-2 bg-diuBlue text-white font-medium rounded-lg hover:bg-blue-700 transition-colors duration-300"
                  >
                    Clear Filters
                  </button>
                </div>
              </motion.div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};

export default HomePage;
