import { Link } from "react-router-dom";
import { FaArrowRight } from "react-icons/fa";
import { motion } from "framer-motion";

const DepartmentCard = ({ department }) => {
  return (
    <motion.div
      initial={{ opacity: 0, y: 50 }}
      whileInView={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.6, ease: "easeOut" }}
      viewport={{ once: true }}
    >
      <Link
        to={`/department/${department.id}`}
        className="group block h-full flex flex-col bg-[#F4F4F4] transition-shadow rounded-xl duration-700 p-4 hover:shadow-md"
      >
        {/* Image */}
        <div className="relative h-48 overflow-hidden rounded-xl">
          <img
            src={department.imageUrl}
            alt={department.name}
            className="w-full h-full object-cover rounded-xl transition-transform duration-700 group-hover:scale-105"
          />
        </div>

        {/* Content */}
        <div className="pt-4 flex-grow flex flex-col ">
          <div className="border-b border-gray-300">
            <h3 className="font-bold text-xl mb-4 text-diuText transition-all duration-300 ease-out group-hover:translate-x-2">
            {department.name}
          </h3>
          </div>

          {/* Button (Styled Box) */}
          <div className="mt-auto pt-4">
            <span className="inline-flex items-center font-bold text-sm group-hover:translate-x-2 transition-transform duration-300 ease-out">
              Learn More
              <FaArrowRight className="ml-2 group-hover:translate-x-1 transition-transform" />
            </span>
          </div>
        </div>
      </Link>
    </motion.div>
  );
};

export default DepartmentCard;
