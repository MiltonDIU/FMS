import { useState, useEffect } from "react";
import { FaChevronDown, FaTimes } from "react-icons/fa";
import { ListFilter } from "lucide-react";
import { motion, AnimatePresence } from "framer-motion";

const RoleFilterSidebar = ({
  facultyRoles,
  managementRoles,
  selectedRole,
  onRoleChange,
}) => {
  const [isExpanded, setIsExpanded] = useState(false);
  const [isSticky, setIsSticky] = useState(false);
  const [isMobile, setIsMobile] = useState(false);

  // Update: Set empty string as default for "All Members"
  useEffect(() => {
    if (selectedRole === null || selectedRole === undefined) {
      onRoleChange(""); // Empty string will show all members
    }
  }, []);

  useEffect(() => {
    const handleScroll = () => setIsSticky(window.scrollY > 100);
    const handleResize = () => {
      const mobile = window.innerWidth < 768;
      setIsMobile(mobile);
      setIsExpanded(!mobile); // Expanded on desktop, collapsed on mobile
    };

    handleResize();
    window.addEventListener("scroll", handleScroll);
    window.addEventListener("resize", handleResize);

    return () => {
      window.removeEventListener("scroll", handleScroll);
      window.removeEventListener("resize", handleResize);
    };
  }, []);

  const toggleExpand = () => setIsExpanded(!isExpanded);

  const handleRoleChange = (roleValue) => {
    onRoleChange(roleValue);
    if (isMobile) setIsExpanded(false);
  };

  /*** Overlay for Mobile ***/
  const Overlay = () => (
    <motion.div
      initial={{ opacity: 0 }}
      animate={{ opacity: 1 }}
      exit={{ opacity: 0 }}
      className="fixed inset-0 bg-black bg-opacity-50 z-40 md:hidden"
      onClick={() => setIsExpanded(false)}
    />
  );

  /*** Mobile Sidebar (Bottom Sheet) ***/
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
        if (info.offset.y > 100) setIsExpanded(false);
      }}
      className="fixed bottom-0 left-0 right-0 h-[80vh] bg-white shadow-2xl z-50 md:hidden rounded-t-[32px] overflow-hidden px-4"
    >
      <div className="h-full flex flex-col">
        {/* Handle bar */}
        <div className="pt-4 pb-2 flex justify-center">
          <div className="w-12 h-1.5 bg-gray-300 rounded-full"></div>
        </div>

        {/* Header */}
        <div className="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
          <h3 className="text-xl font-bold text-gray-800 flex items-center gap-2">
            <ListFilter className="w-6 h-6" /> Filter by Role ({managementRoles.length + facultyRoles.length})
          </h3>
          <button
            onClick={() => setIsExpanded(false)}
            className="p-2 hover:bg-gray-100 rounded-full transition-colors"
          >
            <FaTimes size={22} className="text-gray-500" />
          </button>
        </div>

        {/* Filter Options */}
        <div className="flex-1 overflow-y-auto">
          <FilterOptions />
        </div>
      </div>
    </motion.div>
  );

  /*** Shared Filter Options for Desktop & Mobile ***/
  const FilterOptions = () => (
    <>
      {/* All Members Section */}
      <div
        onClick={() => handleRoleChange("")}
        className={`cursor-pointer py-4 px-4 transition-all duration-200 border-l-[5px] border-b border-gray-100
          hover:bg-blue-50 hover:text-blueText ${
            !selectedRole || selectedRole === ""
              ? "border-l-blue-900 bg-blue-50 text-blueText"
              : "border-l-transparent"
          }`}
      >
        <span className="text-xl lg:text-diu text-[#2F2F2F] font-medium text-wrap lg:truncate">
          All Management & Faculty Members
        </span>
      </div>

      {/* Management Section */}
      <h4 className="text-xl lg:text-md text-gray-500 font-medium truncate pl-4">
        Departmental Management
      </h4>
      {managementRoles.map((role) => (
        <div
          key={role}
          onClick={() => handleRoleChange(role)}
          className={`cursor-pointer py-2 px-4 transition-all duration-200 border-l-[5px] 
            hover:bg-blue-50 hover:text-blueText ${
              selectedRole === role
                ? "border-l-blue-900 bg-blue-50 text-blueText"
                : "border-l-transparent"
            }`}
        >
          <span className="text-xl lg:text-diu text-[#2F2F2F] font-medium truncate">
            {role}
          </span>
        </div>
      ))}

      {/* Faculty Members Section */}
      <h4 className="text-xl lg:text-md text-gray-500 font-medium text-wrap lg:truncate pl-4 pt-2 border-t border-gray-100">
        Departmental Faculty Members
      </h4>
      {facultyRoles.map((role) => (
        <div
          key={role}
          onClick={() => handleRoleChange(role)}
          className={`cursor-pointer py-2 px-4 transition-all duration-200 border-l-[5px] 
            hover:bg-blue-50 hover:text-blueText ${
              selectedRole === role
                ? "border-l-blue-900 bg-blue-50 text-blueText"
                : "border-l-transparent"
            }`}
        >
          <span className="text-diu text-[#2F2F2F] font-medium truncate">
            {role}
          </span>
        </div>
      ))}
    </>
  );

  return (
    <>
      {/* 📱 Mobile Filter Button */}
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
              <ListFilter size={22} className="text-gray-600" />
              <div>
                <h3 className="font-semibold text-diuBlue text-base">
                  Filter by Role ({managementRoles.length + facultyRoles.length})
                </h3>
                <p className="text-sm text-gray-500 font-semibold">
                  {selectedRole === "all"
                    ? "All Members"
                    : selectedRole || "All Members"}
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
        /* 💻 Desktop Sidebar */
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
          <div className={`flex justify-between items-center mb-6 ${
            isMobile ? 'border-b p-2 border-gray-100' : 'p-2'
          }`}>
            <motion.h3
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.2, duration: 0.4 }}
              className="text-xl font-bold text-[#58595B]"
            >
              Filter by Role ({managementRoles.length + facultyRoles.length})
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

      {/* 📱 Mobile Overlay + Sidebar */}
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

export default RoleFilterSidebar;
