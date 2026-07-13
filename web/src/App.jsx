import { Routes, Route } from "react-router-dom";

import Header from "./components/common/Header";

import Footer from "./components/common/Footer";

import HomePage from "./pages/HomePage";

import DepartmentPage from "./pages/DepartmentPage";

import FacultyProfilePage from "./pages/FacultyProfilePage";

import NotFoundPage from "./pages/NotFoundPage";

import ScrollToTop from "./components/common/ScrollToTop";

import DepartmentContactPage from "./pages/DepartmentContactPage";

function App() {
  return (
    <div className="flex flex-col min-h-screen">
      <Header />

      <main className="flex-grow">
        <ScrollToTop />

        <Routes>
          <Route path="/" element={<HomePage />} />

          <Route path="/department/:deptId" element={<DepartmentPage />} />

          <Route 
            path="/department/:deptId/contact" 
            element={<DepartmentContactPage />} 
          />

          <Route path="/faculty/:facultyId" element={<FacultyProfilePage />} />

          <Route path="*" element={<NotFoundPage />} />
        </Routes>
      </main>

      <Footer />
    </div>
  );
}

export default App;
