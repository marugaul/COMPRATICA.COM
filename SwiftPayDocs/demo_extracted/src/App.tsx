import { BrowserRouter, Routes, Route } from "react-router-dom";
import Home from "./pages/Home";
import { Validate } from "./pages/Validate";
import { Auth } from "./pages/Auth";
import { ThreeDsResult } from "./pages/ThreeDsResult";
import { Complete } from "./pages/Complete";
import { Void } from "./pages/Void";
import { PreAuth } from "./pages/PreAuth";
import { Navbar } from "./pages/Navbar";



function App() {

  return (

    <BrowserRouter>

      <Navbar />

      {/* Contenido cambia */}
      <div className="container mt-3">

        <Routes>



          <Route path="/" element={<Home />} />

          <Route path="/validate" element={<Validate />} />
          <Route path="/auth" element={<Auth />} />
          <Route path="/3ds" element={<ThreeDsResult />} />
          <Route path="/complete" element={<Complete />} />
          <Route path="/void" element={<Void />} />
          <Route path="/preauth" element={<PreAuth />} />

        </Routes>
      </div>

    </BrowserRouter>

  )

}

export default App;