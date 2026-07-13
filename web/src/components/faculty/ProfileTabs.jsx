import { div } from "framer-motion/client";
import { useState, useRef } from "react";
import {
  FaChevronLeft,
  FaChevronRight,
  FaUser,
  FaBook,
  FaFlask,
  FaFileAlt,
  FaAward,
} from "react-icons/fa";

const ProfileTabs = ({ faculty }) => {
  const [activeTab, setActiveTab] = useState("overview");
  const [expandedSections, setExpandedSections] = useState({});
  const scrollContainerRef = useRef(null);

  const handleTabChange = (tab) => {
    setActiveTab(tab);
  };

  const scrollTabs = (direction) => {
    if (scrollContainerRef.current) {
      const container = scrollContainerRef.current;
      const scrollAmount = 200; // Adjust scroll amount as needed

      if (direction === "left") {
        container.scrollBy({ left: -scrollAmount, behavior: "smooth" });
      } else {
        container.scrollBy({ left: scrollAmount, behavior: "smooth" });
      }
    }
  };

  const toggleSection = (sectionName) => {
    setExpandedSections((prev) => ({
      ...prev,
      [sectionName]: !prev[sectionName],
    }));
  };

  const getVisibleItems = (items, sectionName) => {
    if (!items) return [];
    return expandedSections[sectionName] ? items : items.slice(0, 6);
  };

  const tabConfig = [
    {
      id: "overview",
      label: "Overview",
      icon: <FaUser />,
      description: "Biography, Education, and Expertise",
    },
    {
      id: "courses",
      label: "Courses",
      icon: <FaBook />,
      description: "Current and Previous Teaching",
    },
    {
      id: "research",
      label: "Research",
      icon: <FaFlask />,
      description: "Projects, Interests, and Collaborations",
    },
    {
      id: "publications",
      label: "Publications",
      icon: <FaFileAlt />,
      description: "Papers, Articles, and Books",
    },
    {
      id: "trainingExperience",
      label: "Training Experience",
      icon: <FaFileAlt />,
      description: "Professional Training and Workshops",
    },
    {
      id: "awardsAndScholarships",
      label: "Awards & Scholarships",
      icon: <FaAward />,
      description: "Recognitions and Achievements",
    },
    {
      id: "memberships",
      label: "Memberships",
      icon: <FaFileAlt />,
      description: "Professional Affiliations",
    },
  ];

  return (
    <div className="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
      {/* Sticky Tab Navigation */}
      <div className="bg-white/95 backdrop-blur-md border-b border-gray-200 shadow-sm">
        {/* Left Arrow - Mobile Only */}
        <button
          onClick={() => scrollTabs("left")}
          className="absolute left-2 top-1/2 transform -translate-y-1/2 z-10 w-8 h-8 bg-white/80 hover:bg-white rounded-full shadow-md flex items-center justify-center text-gray-600 hover:text-gray-800 transition-all duration-300 md:hidden backdrop-blur-sm"
          aria-label="Scroll tabs left"
        >
          <FaChevronLeft className="text-sm" />
        </button>

        {/* Right Arrow - Mobile Only */}
        <button
          onClick={() => scrollTabs("right")}
          className="absolute right-2 top-1/2 transform -translate-y-1/2 z-10 w-8 h-8 bg-white/80 hover:bg-white rounded-full shadow-md flex items-center justify-center text-gray-600 hover:text-gray-800 transition-all duration-300 md:hidden backdrop-blur-sm"
          aria-label="Scroll tabs right"
        >
          <FaChevronRight className="text-sm" />
        </button>

        {/* Tab Container with Padding for Arrows */}
        <div
          className="flex overflow-x-auto scrollbar-hide px-12 md:px-0 justify-between"
          ref={scrollContainerRef}
        >
          {tabConfig?.map((tab) => (
            <button
              key={tab.id}
              className={`relative flex flex-col items-center px-4 py-3 min-w-[120px] md:min-w-[140px] focus:outline-none transition-all duration-300 ease-in-out group ${
                activeTab === tab.id
                  ? "bg-gray-100/80 shadow-sm border-b-2 border-diuBlue"
                  : "hover:bg-gray-100/60"
              }`}
              onClick={() => handleTabChange(tab.id)}
              aria-label={`Switch to ${tab.label} tab`}
            >
              <div className="flex gap-2 py-2">
                <span className="text-lg md:text-xl text-diuBlue">
                  {tab?.icon}
                </span>
                <span
                  className={`font-bold text-xs md:text-sm text-start transition-colors ${
                    activeTab === tab.id
                      ? "text-diuBlue font-bold"
                      : "text-gray-600 group-hover:text-diuBlue"
                  }`}
                >
                  {tab?.label}
                </span>
              </div>
              {/* <span className="text-xs text-gray-400 hidden md:block mt-1">
                {tab.description}
              </span> */}

              {activeTab === tab.id && (
                <div className="absolute bottom-0 left-0 right-0 h-0.5 bg-diuBlue animate-pulse"></div>
              )}
            </button>
          ))}
        </div>
      </div>

      {/* Modified content sections with See More functionality */}
      <div className="p-4 md:p-6 lg:p-8">
        <div className="animate-fadeIn">
          {activeTab === "overview" && (
            <div className="gap-4 md:gap-8">
              {/* Biography Section */}
              <div className="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-6 border border-blue-100 mb-4">
                <div className="flex items-center mb-2">
                  <h2 className="text-xl md:text-2xl font-bold text-gray-800">
                    Biography
                  </h2>
                </div>
                <p className="text-gray-700 leading-relaxed text-sm md:text-base whitespace-pre-line">
                  {faculty?.bio}
                </p>
              </div>

              {/* Education Section */}
              <div className="flex items-center mb-2">
                <h3 className="text-lg md:text-xl font-bold text-gray-800">
                  Education
                </h3>
              </div>

              <div className="space-y-4">
                {Array.from({
                  length: Math.ceil(faculty?.education?.length / 2 || 0),
                }).map((_, rowIndex) => (
                  <div
                    className="grid grid-cols-1 md:grid-cols-2 gap-8"
                    key={rowIndex}
                  >
                    {faculty?.education
                      ?.slice(rowIndex * 2, rowIndex * 2 + 2)
                      ?.map((edu, index) => (
                        <div
                          className="bg-white rounded-lg p-4 border-l-4 border-blue-900 shadow-sm hover:shadow-md transition-shadow duration-300"
                          key={index}
                        >
                          <div className="flex items-start justify-between">
                            <div className="flex-1">
                              <p className="font-semibold text-gray-800 text-sm">
                                {edu.degree}
                              </p>
                              <p className="text-gray-600 text-sm mt-1">
                                {edu.institution}
                              </p>
                            </div>
                            <span className="bg-gray-200 text-blueText px-3 py-1 rounded-full text-xs font-medium">
                              {edu.year}
                            </span>
                          </div>
                        </div>
                      ))}
                    {faculty?.education?.length % 2 !== 0 &&
                      rowIndex ===
                        Math.floor(faculty?.education?.length / 2) && (
                        <div className="bg-transparent"></div>
                      )}
                  </div>
                ))}
              </div>

              {/* Expertise Section - Full Width */}
              <div className="md:col-span-2 mt-4">
                <div className="flex items-center mb-2">
                  <h3 className="text-lg md:text-xl font-bold text-gray-800">
                    Areas of Expertise
                  </h3>
                </div>
                <div className="flex flex-wrap gap-3">
                  {faculty?.expertise?.map((area, index) => (
                    <span
                      key={index}
                      className="bg-diuBlue text-white px-4 py-2 rounded-full text-sm font-medium hover:shadow-sm transition-all duration-300 transform hover:scale-105"
                    >
                      {area}
                    </span>
                  ))}
                </div>
              </div>
            </div>
          )}

          {activeTab === "courses" && (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
              {/* Current Courses */}
              <div>
                <div className="flex items-center mb-2">
                  <h2 className="text-xl md:text-2xl font-bold text-gray-800">
                    Current Courses
                  </h2>
                </div>
                <div className="space-y-4">
                  {faculty?.courses?.current?.map((course, index) => (
                    <div
                      key={index}
                      className="bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl p-5 border border-green-200 hover:shadow-md transition-all duration-300"
                    >
                      <div className="flex items-start justify-between">
                        <div className="flex-1">
                          <div className="flex items-center mb-2">
                            <p className="font-semibold text-gray-800 text-sm md:text-base">
                              {course.code}
                            </p>
                          </div>
                          <p className="text-gray-700 text-sm md:text-base font-medium">
                            {course.name}
                          </p>
                        </div>
                        <span className="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-medium">
                          Active
                        </span>
                      </div>
                      <p className="text-gray-500 text-sm mt-3 flex items-center">
                        {course.semester}
                      </p>
                    </div>
                  ))}
                </div>
              </div>

              {/* Previous Courses */}
              <div>
                <div className="flex items-center mb-2">
                  <h2 className="text-xl md:text-2xl font-bold text-gray-800">
                    Previous Courses
                  </h2>
                </div>
                <div className="space-y-4">
                  {faculty?.courses?.previous?.map((course, index) => (
                    <div
                      key={index}
                      className="bg-gray-50 rounded-xl p-5 border border-gray-200 hover:shadow-md transition-all duration-300"
                    >
                      <div className="flex items-start justify-between">
                        <div className="flex-1">
                          <div className="flex items-center mb-2">
                            <p className="font-semibold text-gray-700 text-sm md:text-base">
                              {course.code}
                            </p>
                          </div>
                          <p className="text-gray-600 text-sm md:text-base font-medium">
                            {course.name}
                          </p>
                        </div>
                        <span className="bg-gray-200 text-gray-600 px-3 py-1 rounded-full text-xs font-medium">
                          Completed
                        </span>
                      </div>
                      <p className="text-gray-500 text-sm mt-3 flex items-center">
                        {course.semester}
                      </p>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          )}

          {activeTab === "research" && (
            <div className="">
              <div className="bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl p-6 border border-purple-100 mb-4">
                <div className="flex items-center mb-4">
                  <h2 className="text-xl md:text-2xl font-bold text-gray-800">
                    Research
                  </h2>
                </div>

                {/* Publications */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  {faculty?.research?.publications?.map((pub, index) => (
                    <div key={index} className=" space-y-2">
                      <p className="text-gray-800 font-semibold text-sm md:text-base">
                        {pub.title}
                      </p>
                      <p className=" text-xs md:text-sm font-medium">
                        Publisher: {pub.publisher}
                      </p>
                      <p className="text-gray-600">Year: {pub.year}</p>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          )}

          {activeTab === "publications" && (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {/* Books Section */}
              {faculty?.books && faculty?.books?.length > 0 && (
                <>
                  <div className="md:col-span-2">
                    <div className="flex items-center mb-2">
                      <h3 className="text-lg md:text-xl font-bold text-gray-800">
                        Books
                      </h3>
                    </div>
                  </div>
                  {getVisibleItems(faculty?.books, "books")?.map(
                    (book, index) => (
                      <div
                        key={`book-${index}`}
                        className="bg-white rounded-xl p-6 border border-gray-200 hover:shadow-md transition-all duration-300"
                      >
                        <div className="flex items-start justify-between mb-3">
                          <h3 className="font-bold text-gray-800 text-sm md:text-base leading-relaxed">
                            {book.title}
                          </h3>
                          <span className="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs font-medium ml-4">
                            {book.year}
                          </span>
                        </div>
                        <div className="space-y-2 mb-4">
                          <p className="text-gray-700 text-sm md:text-base font-medium">
                            {book.publisher}
                          </p>
                          {(book.courseCode || book.language) && (
                            <p className="text-gray-500 text-sm">
                              {[
                                book.courseCode &&
                                  `Course Code: ${book.courseCode}`,
                                book.language && `Language: ${book.language}`,
                              ]
                                .filter(Boolean)
                                .join(", ")}
                            </p>
                          )}
                        </div>
                        {book.url && (
                          <div className="flex items-center">
                            <a
                              href={book.url}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="inline-flex items-center border border-diuBlue hover:bg-diuBlue text-diuBlue hover:text-white px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300 transform hover:scale-105 hover:shadow-lg"
                            >
                              <span className="mr-2">🔗</span>
                              View Book
                            </a>
                          </div>
                        )}
                      </div>
                    )
                  )}
                  {faculty?.books?.length > 6 && (
                    <div className="md:col-span-2 flex justify-center mt-4">
                      <button
                        onClick={() => toggleSection("books")}
                        className="bg-white hover:bg-gray-50 text-primary-600 font-medium py-2 px-4 rounded-lg border border-primary-200 shadow-sm transition-all duration-300"
                      >
                        {expandedSections["books"]
                          ? "Show Less"
                          : `Show More (${faculty.books.length - 6} items)`}
                      </button>
                    </div>
                  )}
                </>
              )}

              {/* Papers Section */}
              {getVisibleItems(faculty?.publications, "publications")?.map(
                (pub, index) => (
                  <div
                    key={`pub-${index}`}
                    className="bg-white rounded-xl p-6 border border-gray-200 hover:shadow-md transition-all duration-300"
                  >
                    <div className="flex items-start justify-between mb-3">
                      <h3 className="font-bold text-gray-800 text-sm md:text-base leading-relaxed">
                        {pub.title}
                      </h3>
                      <span className="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs font-medium ml-4">
                        {pub.year}
                      </span>
                    </div>
                    <div className="space-y-2 mb-4">
                      <p className="text-gray-600 italic text-sm md:text-base">
                        {pub.authors.join(", ")}
                      </p>
                      <p className="text-gray-700 text-sm md:text-base font-medium">
                        {pub.journal}
                      </p>
                      {(pub.volume || pub.issue || pub.issn || pub.index) && (
                        <p className="text-gray-500 text-sm">
                          {[
                            pub.volume && `Volume ${pub.volume}`,
                            pub.issue && `Issue ${pub.issue}`,
                            pub.issn && `ISSN: ${pub.issn}`,
                            pub.index && `Indexed in ${pub.index}`,
                          ]
                            .filter(Boolean)
                            .join(", ")}
                        </p>
                      )}
                    </div>
                    {pub.url && (
                      <div className="flex items-center">
                        <a
                          href={pub.url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="inline-flex items-center border border-diuBlue hover:bg-diuBlue text-diuBlue hover:text-white px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300 transform hover:scale-105 hover:shadow-lg"
                        >
                          <span className="mr-2">🔗</span>
                          View Publication
                        </a>
                      </div>
                    )}
                  </div>
                )
              )}
              {faculty?.publications?.length > 6 && (
                <div className="md:col-span-2 flex justify-center mt-4">
                  <button
                    onClick={() => toggleSection("publications")}
                    className="bg-white hover:bg-gray-50 text-diuBlue font-medium py-2 px-4 rounded-lg border border-diuBlue shadow-sm transition-all duration-300"
                  >
                    {expandedSections["publications"]
                      ? "Show Less"
                      : `Show More (${faculty.publications.length - 6} items)`}
                  </button>
                </div>
              )}
            </div>
          )}

          {activeTab === "trainingExperience" && (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="md:col-span-2">
                <div className="flex items-center mb-2">
                  <h2 className="text-xl md:text-2xl font-bold text-gray-800">
                    Training Experience
                  </h2>
                </div>
              </div>

              {getVisibleItems(faculty?.trainingExperience, "training")?.map(
                (training, index) => (
                  <div
                    key={index}
                    className="bg-white rounded-xl p-6 border border-gray-200 hover:shadow-md transition-all duration-300"
                  >
                    <div className="items-start mb-3">
                      <h3 className="font-bold text-gray-800 text-sm md:text-base leading-relaxed">
                        {training.title}
                      </h3>
                      <span className=" text-blue-700 text-sm font-medium">
                        {training.date}
                      </span>
                    </div>
                    <div className="space-y-2">
                      <p className="text-gray-700 text-sm md:text-base font-medium">
                        {training.institution}
                      </p>
                      <p className="text-gray-500 text-sm">
                        {training.location}
                      </p>
                    </div>
                  </div>
                )
              )}
              {faculty?.trainingExperience?.length > 6 && (
                <div className="md:col-span-2 flex justify-center mt-4">
                  <button
                    onClick={() => toggleSection("training")}
                    className="bg-white hover:bg-gray-50 text-diuBlue font-medium py-2 px-4 rounded-lg border border-diuBlue shadow-sm transition-all duration-300"
                  >
                    {expandedSections["training"]
                      ? "Show Less"
                      : `Show More (${
                          faculty.trainingExperience.length - 6
                        } items)`}
                  </button>
                </div>
              )}
            </div>
          )}

          {activeTab === "awardsAndScholarships" && (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="md:col-span-2">
                <div className="flex items-center mb-2">
                  <h2 className="text-xl md:text-2xl font-bold text-gray-800">
                    Awards & Scholarships
                  </h2>
                </div>
              </div>

              {faculty?.awardsAndScholarships?.map((award, index) => (
                <div
                  key={index}
                  className="bg-white rounded-xl p-6 border border-gray-200 hover:shadow-md transition-all duration-300"
                >
                  <div className="flex items-start justify-between mb-3">
                    <h3 className="font-bold text-gray-800 text-sm md:text-base leading-relaxed">
                      {award.title}
                    </h3>
                    {award.year && (
                      <span className="bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-xs font-medium ml-4">
                        {award.year}
                      </span>
                    )}
                  </div>
                  <div className="space-y-2 mb-4">
                    <p className="text-gray-700 text-sm md:text-base font-medium">
                      {award.issuer || "Not specified"}
                    </p>
                  </div>
                </div>
              ))}
            </div>
          )}

          {activeTab === "memberships" && (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="md:col-span-2">
                <div className="flex items-center mb-2">
                  <h2 className="text-xl md:text-2xl font-bold text-gray-800">
                    Professional Memberships
                  </h2>
                </div>
              </div>

              {faculty?.memberships?.map((membership, index) => (
                <div
                  key={index}
                  className="bg-white rounded-xl p-6 border border-gray-200 hover:shadow-md transition-all duration-300"
                >
                  <div className="flex items-start justify-between mb-3">
                    <h3 className="font-bold text-gray-800 text-sm md:text-base leading-relaxed">
                      {membership.title}
                    </h3>
                  </div>
                  <div className="space-y-2 mb-4">
                    <p className="text-gray-700 text-sm md:text-base font-medium">
                      {membership.organization}
                    </p>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default ProfileTabs;
