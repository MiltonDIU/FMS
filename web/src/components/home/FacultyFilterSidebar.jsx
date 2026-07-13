import { useState, useEffect } from "react";
import { FaChevronDown, FaChevronUp, FaTimes } from "react-icons/fa";
import { ListFilter } from "lucide-react";
import { motion, AnimatePresence } from "framer-motion";

const FacultyFilterSidebar = ({
  faculties,
  selectedFaculty,
  onFacultyChange,
}) => {
  const [isExpanded, setIsExpanded] = useState(false);
  const [isSticky, setIsSticky] = useState(false);
  const [isMobile, setIsMobile] = useState(false);

  // Set first faculty as default active on mount
  useEffect(() => {
    if (faculties.length > 0 && !selectedFaculty) {
      onFacultyChange(faculties[0].id);
    }
  }, []);

  useEffect(() => {
    const handleScroll = () => {
      setIsSticky(window.scrollY > 100);
    };

    const handleResize = () => {
      const mobile = window.innerWidth < 768;
      setIsMobile(mobile);
      setIsExpanded(!mobile); // Expanded by default on desktop, collapsed on mobile
    };

    handleResize(); // Initial check
    window.addEventListener("scroll", handleScroll);
    window.addEventListener("resize", handleResize);

    return () => {
      window.removeEventListener("scroll", handleScroll);
      window.removeEventListener("resize", handleResize);
    };
  }, []);

  const toggleExpand = () => {
    setIsExpanded(!isExpanded);
  };

  const handleFacultyChange = (facultyId) => {
    onFacultyChange(facultyId);
    // Close sidebar on mobile after selection
    if (isMobile) {
      setIsExpanded(false);
    }
  };

  // Mobile overlay when sidebar is open
  const Overlay = () => (
    <motion.div
      initial={{ opacity: 0 }}
      animate={{ opacity: 1 }}
      exit={{ opacity: 0 }}
      className="fixed inset-0 bg-black bg-opacity-50 z-40 md:hidden"
      onClick={() => setIsExpanded(false)}
    />
  );

  // Mobile sidebar content
  const MobileSidebar = () => (
    <motion.div
      initial={{ y: "100%" }}
      animate={{ y: 0 }}
      exit={{ y: "100%" }}
      transition={{
        type: "spring",
        damping: 25,
        stiffness: 120,
        mass: 0.8,
      }}
      drag="y"
      dragConstraints={{ top: 0 }}
      dragElastic={0.2}
      onDragEnd={(_, info) => {
        if (info.offset.y > 100) {
          setIsExpanded(false);
        }
      }}
      className="fixed bottom-0 left-0 right-0 h-[80vh] bg-white shadow-2xl z-50 md:hidden rounded-t-[32px] overflow-hidden px-4"
    >
      <div className="h-full flex flex-col">
        {/* Handle bar for bottom sheet */}
        <div className="pt-4 pb-2 flex justify-center">
          <div className="w-12 h-1.5 bg-gray-300 rounded-full"></div>
        </div>

        <div className="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
          <h3 className="text-xl font-bold text-gray-800 flex items-center gap-2">
            <ListFilter className="w-6 h-6" /> Filter by Faculty (
            {faculties.length})
          </h3>
          <button
            onClick={() => setIsExpanded(false)}
            className="p-2 hover:bg-gray-100 rounded-full transition-colors"
          >
            <FaTimes size={24} className="text-gray-500" />
          </button>
        </div>

        <div className="flex-1 overflow-y-auto">
          {faculties.map((faculty) => (
            <div
              key={faculty.id}
              onClick={() => handleFacultyChange(faculty.id)}
              className={`cursor-pointer py-4 px-2 transition-all border-l-[5px] duration-200 border-b border-gray-100
                ${
                  selectedFaculty === faculty.id
                    ? "border-l-blue-700 bg-blue-50 text-blueText"
                    : "border-l-transparent"
                }
              `}
            >
              <span className="text-xl font-medium">{faculty.name}</span>
            </div>
          ))}
        </div>
      </div>
    </motion.div>
  );

  // Filter options component (reusable for mobile and desktop)
  const FilterOptions = () => (
    <>
      {faculties.map((faculty) => (
        <div
          key={faculty.id}
          onClick={() => handleFacultyChange(faculty.id)}
          className={`cursor-pointer py-3 px-4 transition-all duration-200 border-l-[5px] hover:bg-blue-50 hover:text-blueText
            ${
              selectedFaculty === faculty.id
                ? "border-l-blue-700 bg-blue-50 text-blueText"
                : "border-l-transparent"
            }
          `}
        >
          <span className="text-diu text-[#2F2F2F] font-medium truncate">
            {faculty.name}
          </span>
        </div>
      ))}
    </>
  );

  return (
    <>
      {/* Mobile: Filter Card Button */}
      {isMobile ? (
        <motion.div
          initial={{ scale: 0.95, opacity: 0 }}
          animate={{ scale: 1, opacity: 1 }}
          whileHover={{ scale: 1.02 }}
          whileTap={{ scale: 0.98 }}
          onClick={toggleExpand}
          className="bg-white rounded-xl p-4 shadow-sm transition-all duration-300 border border-gray-200 cursor-pointer w-full mx-auto"
        >
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="relative">
                <ListFilter size={24} className="text-gray-600" />
              </div>
              <div>
                <h3 className="font-semibold text-diuBlue text-base">
                  Filter by Faculty ({faculties.length})
                </h3>
                <p className="text-sm text-gray-500 font-semibold">
                  {selectedFaculty
                    ? faculties.find((f) => f.id === selectedFaculty)?.name ||
                      "Selected"
                    : "All Faculties"}
                </p>
              </div>
            </div>
            <motion.div
              animate={{ rotate: isExpanded ? 180 : 0 }}
              transition={{ duration: 0.2 }}
              className="text-gray-400"
            >
              <FaChevronDown size={16} />
            </motion.div>
          </div>
        </motion.div>
      ) : (
        /* Desktop: Fixed Sidebar */
        <motion.div
          initial={{ opacity: 0, y: 50 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{
            duration: 0.5,
            ease: "easeOut",
          }}
          className={`transition-all duration-300 w-full ${
            isSticky ? "sticky top-20" : ""
          }`}
        >
          <div className="flex justify-between items-center mb-6 border-b p-2 border-gray-100">
            <motion.h3
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.2, duration: 0.4 }}
              className="text-xl font-bold text-[#58595B]"
            >
              Filter by Faculty ({faculties.length})
            </motion.h3>
          </div>

          <motion.div
            initial={{ opacity: 0, y: 30 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.3, duration: 0.5 }}
            className="space-y-2 border-l border-gray-200"
          >
            <FilterOptions />
          </motion.div>
        </motion.div>
      )}

      {/* Mobile Sidebar and Overlay */}
      <AnimatePresence>
        {isMobile && isExpanded && (
          <>
            <Overlay />
            <MobileSidebar />
          </>
        )}
      </AnimatePresence>
    </>
  );
};

export default FacultyFilterSidebar;
