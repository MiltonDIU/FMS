import { Link } from "react-router-dom";

const Hero = () => {
  return (
    <div className=" text-center mt-36 ">
      <div className="container mx-auto px-4  z-10 border-b border-gray-200 pb-10">
        <div className="max-w-4xl mx-auto">
          <h1 className="text-3xl md:text-5xl font-extrabold mb-4 leading-tight text-diuBlue">
            Welcome to the DIU Faculty Directory
          </h1>
          <p className="text-base md:text-lg text-neutral-600 text-center">
            Discover our distinguished faculty members across various
            departments and explore their expertise, research interests, and
            achievements.
          </p>
        </div>
      </div>
    </div>
  );
};

export default Hero;
