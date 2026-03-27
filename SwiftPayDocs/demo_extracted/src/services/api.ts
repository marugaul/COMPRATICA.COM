import axios from "axios";

// Sandbox
const BASE_URL = "https://swiftportals.com/api/card";
// Producción


const dio = axios.create({
    baseURL: BASE_URL,
    timeout: 30000,
    headers: {
        "Content-Type": "application/json"
    }
});


/*
Interceptor Request (como Dio)
*/
dio.interceptors.request.use((config) => {

    console.log("REQUEST:");
    console.log(config.url);
    console.log(config.data);

    return config;

});


/*
Interceptor Response
*/
dio.interceptors.response.use((response) => {

    console.log("RESPONSE:");
    console.log(response.data);

    return response;

}, (error) => {

    console.log("ERROR:");
    console.log(error);

    return Promise.reject(error);

});


export const Api = {

    validateCard: (data: any) =>
        dio.post("/qa/validateCardExternal", data),

    authorization: (data: any) =>
        dio.post("/qa/paymentExternal", data),

    preAuth: (data: any) =>
        dio.post("/qa/preauthExternal", data),

    complete: (data: any) =>
        dio.post("/qa/completeAuthExternal", data),

    void: (data: any) =>
        dio.post("/qa/voidExternal", data),

    query3ds: (clientId: any) =>
        dio.get("/qa/getResult3ds/" + clientId)

};

export const token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJtZXJjaGFudF9pZCI6MTUsImlhdCI6MTc3MjA3NTE3OH0.O29R_kJ0T5xZWI8_9QiUU7eR6edouBYkwuXqYe6AZSM";